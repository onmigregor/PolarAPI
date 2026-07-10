<?php

namespace Modules\Report\Actions;

use Modules\Analytics\Services\TenantConnectionService;
use Modules\CompanyRoute\Models\CompanyRoute;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ExportEpRequestsCsvAction
{
    public array $errors = [];

    public function __construct(
        private TenantConnectionService $tenantService
    ) {}

    public function execute(string $tableName, ?string $startDate = null, ?string $endDate = null): array
    {
        $this->errors = [];
        $clients = $this->tenantService->resolveClients();

        // 1. Obtener datos de la BD central (logins, territorios y catálogos de clientes)
        $territories = DB::table('master_company_territories')->get()->keyBy('try_code');
        $logins = DB::table('master_company_logins')->get()->keyBy('lgn_code');
        $segments = DB::table('master_clients_segments')->get();
        $clientTypes = DB::table('master_clients_type2')->get();

        $allRows = [];

        $tenantResults = $this->tenantService->forEachTenant($clients, function ($client) use ($tableName, $territories, $logins, $segments, $clientTypes, $startDate, $endDate) {
            $routeCode = strtoupper($client->code ?? '');
            
            // Si la tabla no existe en este tenant, ignorar
            if (!\Illuminate\Support\Facades\Schema::connection('tenant')->hasTable($tableName)) {
                return [];
            }

            $query = DB::connection('tenant')->table($tableName);

            // Filtrar por rango de fechas si se proporciona
            if ($startDate) {
                $query->whereDate('created_at', '>=', Carbon::parse($startDate)->format('Y-m-d'));
            }
            if ($endDate) {
                $query->whereDate('created_at', '<=', Carbon::parse($endDate)->format('Y-m-d'));
            }

            // Consultar datos de la tabla EP
            $epData = $query->get();
            $tenantRows = [];

            // Precargar datos de la tabla oficial de clientes para hacer fallback
            $ceps = $epData->map(function ($row) {
                return $row->codigo_cep_interno ?? ($row->cliente_id ?? null);
            })->filter()->unique()->toArray();

            $oficialClientes = [];
            if (!empty($ceps) && \Illuminate\Support\Facades\Schema::connection('tenant')->hasTable('clientes')) {
                $oficialClientes = DB::connection('tenant')->table('clientes')
                    ->whereIn('cep', $ceps)
                    ->get()
                    ->keyBy('cep');
            }

            // Buscar info de la ruta en BD central
            $tryCode = substr($routeCode, 0, 6); // Ej: V1539A
            $territory = $territories->get($tryCode) ?? null;
            $lgnCode = $territory->lgn_code ?? null;
            $login = $lgnCode ? ($logins->get($lgnCode) ?? null) : null;

            $zonaFq = $tryCode;
            $gdvFq = $login->lgn_street2 ?? '';
            $oficFq = $login->lgn_street1 ?? '';
            $csCod = $login->lgn_street3 ?? '';
            $rutaFq = $tryCode;
            $territorioFq = $login->srg_code ?? '';
            $ciCoordinadorFq = ''; // Vacío por especificación

            // LÓGICA DE MAPEO POR TABLA
            if ($tableName === 'clientes_nuevos_ep') {
                foreach ($epData as $row) {
                    $payload = json_decode($row->payload_json ?? '{}', true);
                    $post = $payload['all_post'] ?? $payload;

                    $tipoDocumento = $post['tipoDocumentoFiscal'] ?? '';
                    $numDocumento = $post['numeroDocumentoFiscal'] ?? ($row->rif_fq ?? ($row->rif ?? ''));
                    $cedula = '';
                    $rif = '';
                    if (strtoupper($tipoDocumento) === 'V' || strtoupper($tipoDocumento) === 'E' || strpos($numDocumento, 'V-') === 0 || strpos($numDocumento, 'E-') === 0) {
                        $cedula = $numDocumento;
                    } else {
                        $rif = $numDocumento;
                    }

                    $cepCode = $row->codigo_cep_interno ?? ($row->cliente_id ?? '');
                    $clienteOficial = $oficialClientes[$cepCode] ?? null;

                    // Resolver Segmento y Tipo de cliente descriptivos
                    $resolvedSeg = $this->resolveSegment($post['segmentoCliente'] ?? ($row->segmento_fq ?? ''), $segments);
                    $resolvedCT = $this->resolveClientType($post['tipoCliente'] ?? ($row->tipo_cliente_fq ?? ''), $clientTypes);

                    $tenantRows[] = [
                        'Feacha de creacion ' => Carbon::parse($row->created_at)->format('d/m/Y'),
                        'Usuario' => $row->usuario_id ?? '',
                        'Nombre de la distribuidora ' => $row->usuario_nombre ?? '',
                        '¿Esta solicitud de creación es por un cambio de razón social?' => strtoupper($post['cambioRazonSocial'] ?? 'NO'),
                        'Codigo cep ' => $cepCode,
                        'Coordenada (X)' => str_replace('.', ',', (string)($row->longitud ?? ($post['coordenadaX'] ?? ''))),
                        'Coordenada (Y)' => str_replace('.', ',', (string)($row->latitud ?? ($post['coordenadaY'] ?? ''))),
                        'Indique el portafolio que desea comercializar' => is_array($post['portafolio'] ?? null) ? implode(', ', $post['portafolio']) : ($row->portafolio_fq ?? ''),
                        'Codigo Exclusividad en bebidas' => '',
                        'Exclusividad en bebidas' => $post['exclusividadBebidas'] ?? ($row->exclusividad_fq ?? ''),
                        'Codigo Presencia de consumo en sitio en el PDV' => '',
                        'Presencia de consumo en sitio en el PDV' => $post['consumoSitioPdv'] ?? ($row->consumo_sitio_fq ?? ''),
                        'Codigo Segmento' => $resolvedSeg['code'],
                        ' Segmento' => $resolvedSeg['name'],
                        '¿El cliente pertenece a una cadena?' => strtoupper($post['perteneceCadena'] ?? ($row->cliente_pertenece_cadena_fq ?? 'NO')),
                        'cadena a la que pertenece el cliente' => $post['cadenaCliente'] ?? ($row->cadenas_fq ?? ''),
                        'Codigo Tipo de cliente' => $resolvedCT['code'],
                        'Tipo de cliente' => $resolvedCT['name'],
                        'Nombre comercial' => $post['nombreComercial'] ?? ($row->nombre_comercial_fq ?? ($row->cliente_nombre ?? '')),
                        'Codigo Teléfono 1 (fijo):' => $post['codigoTelefonoFijo'] ?? ($row->telefono_1_fq_codigo ?? ''),
                        'Teléfono 1 (fijo):' => $post['telefonoFijo'] ?? ($row->telefono_1_fq ?? (optional($clienteOficial)->TelefonoContacto ?? '')),
                        'Codigo Teléfono 2 (celular):' => $post['codigoTelefonoMovil'] ?? ($row->telefono_2_fq_codigo ?? ''),
                        'Teléfono 2 (celular):' => $post['telefonoMovil'] ?? ($row->telefono_2_fq ?? ''),
                        'Nombre de la persona contacto' => $post['nombreContacto'] ?? ($row->persona_contacto_fq ?? (optional($clienteOficial)->PersonaContacto ?? '')),
                        'Apellido de la persona contacto' => $post['apellidoContacto'] ?? ($row->apellido_contacto_fq ?? ''),
                        'Codigo Tipo ubicación ' => $post['tipoUbicacion'] ?? ($row->tipo_ubicacion_fq ?? ''),
                        'Tipo ubicación ' => '',
                        'Correo electrónico' => $post['correoPdv'] ?? ($row->correo_fq ?? (optional($clienteOficial)->email ?? '')),
                        'Observaciones' => $post['observacionesCliente'] ?? ($post['cambioMotivo'] ?? ($row->motivo_cambio_estatus ?? '')),
                        'RIF' => $rif,
                        'Cedula' => $cedula,
                        'Edificio/Casa' => $post['direccionEdificioCasa'] ?? ($row->edificio_casa_fq ?? ''),
                        'Detalle de Edificio/Casa' => $post['detalleEdificioCasa'] ?? ($row->nombre_edif_fq ?? ''),
                        'Piso' => $post['direccionPiso'] ?? ($row->piso_fq ?? ''),
                        'Detalle de Piso' => $post['detallePiso'] ?? ($row->nombre_piso_fq ?? ''),
                        'Local/Oficina' => $post['direccionLocalOficina'] ?? ($row->local_oficina_fq ?? ''),
                        'Detalle de Local/Oficina' => $post['detalleLocalOficina'] ?? ($row->nombre_local_fq ?? ''),
                        'Tipo vía principal' => $post['tipoViaPrincipal'] ?? ($row->tipo_via_ppal_fq ?? ''),
                        'Vía principal ' => $post['viaPrincipalTexto'] ?? ($row->via_ppal_fq ?? ''),
                        'Tipo de vía 1' => $post['tipoVia1'] ?? ($row->tipo_via_via1_fq ?? ''),
                        'Vía 1 ' => $post['via1Texto'] ?? ($row->via_1_fq ?? ''),
                        'Tipo de vía 2' => $post['tipoVia2'] ?? ($row->tipo_via_via2_fq ?? ''),
                        'Vía 2' => $post['via2Texto'] ?? ($row->via_2_fq ?? ''),
                        'Referencia' => $post['direccionReferencia'] ?? ($row->referencia_fq ?? ''),
                        'Detalle de Referencia' => $post['direccionReferenciaDetalle'] ?? ($row->nombre_referencia_fq ?? ''),
                        'Día de visita' => is_array($post['diasVisita'] ?? null) ? implode(', ', $post['diasVisita']) : ($row->dia_visita ?? ''),
                        'ZONA_FQ' => $zonaFq,
                        'GDV_FQ' => $gdvFq,
                        'OFIC_FQ' => $oficFq,
                        'CS_COD' => $csCod,
                        'RUTA_FQ' => $rutaFq,
                        'DESTINATARIO_FQ' => '',
                        'TERRITORIO_FQ' => $territorioFq,
                        'DIVISION_FQ' => '',
                        'CI_coordinador_FQ' => $ciCoordinadorFq,
                    ];
                }
            } elseif ($tableName === 'clientes_cambio_estatus_ep') {
                foreach ($epData as $row) {
                    $payload = json_decode($row->payload_json ?? '{}', true);
                    $post = $payload['all_post'] ?? $payload;
                    $cepCode = $row->codigo_cep_interno ?? ($row->cliente_id ?? '');

                    $tenantRows[] = [
                        'Feacha de creacion ' => Carbon::parse($row->created_at)->format('d/m/Y'),
                        'Usuario' => $row->usuario_id ?? '',
                        'Nombre de la distribuidora ' => $row->usuario_nombre ?? '',
                        'Codigo cep ' => $cepCode,
                        'Cambio de Estatus' => $post['nuevoEstatus'] ?? ($post['cambioEstatus'] ?? ''),
                        'Motivos del cambio de Estatus' => $post['motivoCambioEstatus'] ?? ($post['cambioMotivo'] ?? ''),
                        'Nuevo codigo CEP' => $post['nuevoCep'] ?? ($post['nuevoCodigoCep'] ?? ($post['nuevo_cep'] ?? '')),
                        'CEP del mismo dueño' => $post['cepMismoDueno'] ?? ($post['mismoDueno'] ?? ($post['cep_mismo_dueno'] ?? '')),
                        'CEP duplicado' => $post['cepDuplicado'] ?? ($post['duplicado'] ?? ($post['cep_duplicado'] ?? '')),
                        'Fecha de suspensión (desde)' => $post['fechaSuspensionDesde'] ?? ($post['suspensionDesde'] ?? ($post['fecha_suspension_desde'] ?? '')),
                        'Fecha de suspensión (hasta)' => $post['fechaSuspensionHasta'] ?? ($post['suspensionHasta'] ?? ($post['fecha_suspension_hasta'] ?? '')),
                        'CI_coordinador_FQ' => $ciCoordinadorFq,
                    ];
                }
            } elseif ($tableName === 'clientes_actualizacion_ep') {
                // LÓGICA DE AGRUPACIÓN POR CEP
                $grouped = $epData->groupBy(function ($item) {
                    return $item->codigo_cep_interno ?? ($item->cliente_id ?? 'unknown');
                });

                foreach ($grouped as $cep => $groupRows) {
                    if ($cep === 'unknown' || empty($cep)) {
                        continue;
                    }

                    $sortedRows = $groupRows->sortBy('created_at');
                    $latestRow = $sortedRows->last();

                    // Concatenar tipos de solicitud
                    $types = $sortedRows->map(function ($r) {
                        $p = json_decode($r->payload_json ?? '{}', true);
                        $post = $p['all_post'] ?? $p;
                        return $post['tipo_solicitud'] ?? '';
                    })->filter()->unique()->toArray();
                    
                    $tipoConcatenado = implode('/', $types);

                    // Combinar payloads del grupo
                    $mergedPayload = [];
                    foreach ($sortedRows as $r) {
                        $p = json_decode($r->payload_json ?? '{}', true);
                        $post = $p['all_post'] ?? $p;
                        $mergedPayload = array_merge($mergedPayload, $post);
                    }

                    // Determinar si sólo requiere documentos adjuntos
                    $isAttachmentOnly = true;
                    $attachmentTypes = ['Razon social', 'Direccion Fiscal', 'Actualizacion de documentos'];
                    if (empty($types)) {
                        $isAttachmentOnly = false;
                    } else {
                        foreach ($types as $type) {
                            $t = trim($type);
                            $foundType = false;
                            foreach ($attachmentTypes as $at) {
                                if (strcasecmp($t, $at) === 0 || str_contains(strtolower($t), strtolower($at))) {
                                    $foundType = true;
                                    break;
                                }
                            }
                            if (!$foundType) {
                                $isAttachmentOnly = false;
                                break;
                            }
                        }
                    }

                    $clienteOficial = $oficialClientes[$cep] ?? null;

                    // Resolver Segmento y Tipo de cliente descriptivos
                    $segmentCode = $mergedPayload['segmento'] ?? ($mergedPayload['segmentoCliente'] ?? ($latestRow->segmento_fq ?? ''));
                    $resolvedSeg = $this->resolveSegment($segmentCode, $segments);

                    $clientTypeValue = $mergedPayload['tipoCliente'] ?? ($latestRow->tipo_cliente_fq ?? '');
                    $resolvedCT = $this->resolveClientType($clientTypeValue, $clientTypes);

                    if ($isAttachmentOnly) {
                        $tenantRows[] = [
                            'Feacha de creacion ' => Carbon::parse($latestRow->created_at)->format('d/m/Y'),
                            'Usuario' => $latestRow->usuario_id ?? '',
                            'Nombre de la distribuidora ' => $latestRow->usuario_nombre ?? '',
                            'Codigo cep ' => $cep,
                            'Tipo de solicitud' => $tipoConcatenado,
                            'Codigo Teléfono 1 (fijo):' => '',
                            'Teléfono 1 (fijo):' => '',
                            'Codigo Teléfono 2 (celular):' => '',
                            'Teléfono 2 (celular):' => '',
                            'Nombre de la persona contacto' => '',
                            'Apellido de la persona contacto' => '',
                            'Correo electrónico' => '',
                            'Nombre comercial' => '',
                            'Codigo Exclusividad en bebidas' => '',
                            'Exclusividad en bebidas' => '',
                            'Codigo Presencia de consumo en sitio en el PDV' => '',
                            'Presencia de consumo en sitio en el PDV' => '',
                            'Codigo Segmento' => '',
                            ' Segmento' => '',
                            '¿El cliente pertenece a una cadena?' => '',
                            'cadena a la que pertenece el cliente' => '',
                            'Codigo Tipo de cliente' => '',
                            'Tipo de cliente' => '',
                            'Coordenada (X)' => '',
                            'Coordenada (Y)' => '',
                            'Día de visita propuesto' => '',
                            'Modelo propuesto' => '',
                            'motivo del cambio' => '',
                            'Edificio/Casa' => '',
                            'Detalle de Edificio/Casa' => '',
                            'Piso' => '',
                            'Detalle de Piso' => '',
                            'Local/Oficina' => '',
                            'Detalle de Local/Oficina' => '',
                            'Tipo vía principal' => '',
                            'Vía principal ' => '',
                            'Tipo de vía 1' => '',
                            'Vía 1 ' => '',
                            'Tipo de vía 2' => '',
                            'Vía 2' => '',
                            'Referencia' => '',
                            'Detalle de Referencia' => '',
                        ];
                    } else {
                        $portafolio = is_array($mergedPayload['portafolio'] ?? null) ? implode(', ', $mergedPayload['portafolio']) : ($latestRow->portafolio_fq ?? '');
                        $exclusividad = $mergedPayload['exclusividadBebidas'] ?? ($latestRow->exclusividad_fq ?? '');
                        $consumoSitio = $mergedPayload['consumoSitioPdv'] ?? ($latestRow->consumo_sitio_fq ?? '');
                        $perteneceCadena = strtoupper($mergedPayload['perteneceCadena'] ?? ($latestRow->cliente_pertenece_cadena_fq ?? 'NO'));
                        $cadena = $mergedPayload['cadenaCliente'] ?? ($latestRow->cadenas_fq ?? '');

                        $coordX = $latestRow->longitud ?? ($mergedPayload['coordenadaX'] ?? ($mergedPayload['coordenadaXDireccionFisica'] ?? ''));
                        $coordY = $latestRow->latitud ?? ($mergedPayload['coordenadaY'] ?? ($mergedPayload['coordenadaYDireccionFisica'] ?? ''));

                        $diaVisita = is_array($mergedPayload['diasVisita'] ?? null) ? implode(', ', $mergedPayload['diasVisita']) : 
                                     (is_array($mergedPayload['frecuenciaVisita'] ?? null) ? implode(', ', $mergedPayload['frecuenciaVisita']) : 
                                     ($mergedPayload['cambioDiaVisitaPropuesto'] ?? ($latestRow->dia_visita ?? '')));

                        $modelo = $mergedPayload['cambioModeloPropuesto'] ?? ($latestRow->modelo_propuesto ?? '');
                        $motivo = $mergedPayload['cambioMotivo'] ?? ($latestRow->motivo_cambio_modelo ?? '');

                        $tenantRows[] = [
                            'Feacha de creacion ' => Carbon::parse($latestRow->created_at)->format('d/m/Y'),
                            'Usuario' => $latestRow->usuario_id ?? '',
                            'Nombre de la distribuidora ' => $latestRow->usuario_nombre ?? '',
                            'Codigo cep ' => $cep,
                            'Tipo de solicitud' => $tipoConcatenado,
                            'Codigo Teléfono 1 (fijo):' => $mergedPayload['codigoTelefonoFijo'] ?? ($mergedPayload['tel1Codigo'] ?? ($latestRow->telefono_1_fq_codigo ?? '')),
                            'Teléfono 1 (fijo):' => $mergedPayload['telefonoFijo'] ?? ($mergedPayload['tel1Numero'] ?? ($latestRow->telefono_1_fq ?? (optional($clienteOficial)->TelefonoContacto ?? ''))),
                            'Codigo Teléfono 2 (celular):' => $mergedPayload['codigoTelefonoMovil'] ?? ($mergedPayload['tel2Codigo'] ?? ($latestRow->telefono_2_fq_codigo ?? '')),
                            'Teléfono 2 (celular):' => $mergedPayload['telefonoMovil'] ?? ($mergedPayload['tel2Numero'] ?? ($latestRow->telefono_2_fq ?? '')),
                            'Nombre de la persona contacto' => $mergedPayload['nombreContacto'] ?? ($latestRow->persona_contacto_fq ?? (optional($clienteOficial)->PersonaContacto ?? '')),
                            'Apellido de la persona contacto' => $mergedPayload['apellidoContacto'] ?? ($latestRow->apellido_contacto_fq ?? ''),
                            'Correo electrónico' => $mergedPayload['correoPdv'] ?? ($mergedPayload['correoElectronico'] ?? ($latestRow->correo_fq ?? (optional($clienteOficial)->email ?? ''))),
                            'Nombre comercial' => $mergedPayload['nombreComercial'] ?? ($latestRow->nombre_comercial_fq ?? (optional($clienteOficial)->Cliente ?? '')),
                            'Codigo Exclusividad en bebidas' => '',
                            'Exclusividad en bebidas' => $exclusividad,
                            'Codigo Presencia de consumo en sitio en el PDV' => '',
                            'Presencia de consumo en sitio en el PDV' => $consumoSitio,
                            'Codigo Segmento' => $resolvedSeg['code'],
                            ' Segmento' => $resolvedSeg['name'],
                            '¿El cliente pertenece a una cadena?' => $perteneceCadena,
                            'cadena a la que pertenece el cliente' => $cadena,
                            'Codigo Tipo de cliente' => $resolvedCT['code'],
                            'Tipo de cliente' => $resolvedCT['name'],
                            'Coordenada (X)' => str_replace('.', ',', (string)$coordX),
                            'Coordenada (Y)' => str_replace('.', ',', (string)$coordY),
                            'Día de visita propuesto' => $diaVisita,
                            'Modelo propuesto' => $modelo,
                            'motivo del cambio' => $motivo,
                            'Edificio/Casa' => $mergedPayload['direccionEdificioCasa'] ?? ($latestRow->edificio_casa_fq ?? ''),
                            'Detalle de Edificio/Casa' => $mergedPayload['detalleEdificioCasa'] ?? ($latestRow->nombre_edif_fq ?? ''),
                            'Piso' => $mergedPayload['direccionPiso'] ?? ($latestRow->piso_fq ?? ''),
                            'Detalle de Piso' => $mergedPayload['detallePiso'] ?? ($latestRow->nombre_piso_fq ?? ''),
                            'Local/Oficina' => $mergedPayload['direccionLocalOficina'] ?? ($latestRow->local_oficina_fq ?? ''),
                            'Detalle de Local/Oficina' => $mergedPayload['detalleLocalOficina'] ?? ($latestRow->nombre_local_fq ?? ''),
                            'Tipo vía principal' => $mergedPayload['tipoViaPrincipal'] ?? ($latestRow->tipo_via_ppal_fq ?? ''),
                            'Vía principal ' => $mergedPayload['viaPrincipalTexto'] ?? ($latestRow->via_ppal_fq ?? ''),
                            'Tipo de vía 1' => $mergedPayload['tipoVia1'] ?? ($latestRow->tipo_via_via1_fq ?? ''),
                            'Vía 1 ' => $mergedPayload['via1Texto'] ?? ($latestRow->via_1_fq ?? ''),
                            'Tipo de vía 2' => $mergedPayload['tipoVia2'] ?? ($latestRow->tipo_via_via2_fq ?? ''),
                            'Vía 2' => $mergedPayload['via2Texto'] ?? ($latestRow->via_2_fq ?? ''),
                            'Referencia' => $mergedPayload['direccionReferencia'] ?? ($latestRow->referencia_fq ?? ''),
                            'Detalle de Referencia' => $mergedPayload['direccionReferenciaDetalle'] ?? ($latestRow->nombre_referencia_fq ?? ''),
                        ];
                    }
                }
            }

            return $tenantRows;
        });

        foreach ($tenantResults['results'] as $res) {
            if (!empty($res['data'])) {
                $allRows = array_merge($allRows, $res['data']);
            }
        }

        $this->errors = $tenantResults['errors'] ?? [];
        Log::info("ExportEpRequestsCsvAction ($tableName): Total filas generadas: " . count($allRows));

        return $allRows;
    }

    public function generateCsvContent(array $rows, string $tableName): string
    {
        $headers = [];

        if ($tableName === 'clientes_nuevos_ep') {
            $headers = [
                'Feacha de creacion ',
                'Usuario',
                'Nombre de la distribuidora ',
                '¿Esta solicitud de creación es por un cambio de razón social?',
                'Codigo cep ',
                'Coordenada (X)',
                'Coordenada (Y)',
                'Indique el portafolio que desea comercializar',
                'Codigo Exclusividad en bebidas',
                'Exclusividad en bebidas',
                'Codigo Presencia de consumo en sitio en el PDV',
                'Presencia de consumo en sitio en el PDV',
                'Codigo Segmento',
                ' Segmento',
                '¿El cliente pertenece a una cadena?',
                'cadena a la que pertenece el cliente',
                'Codigo Tipo de cliente',
                'Tipo de cliente',
                'Nombre comercial',
                'Codigo Teléfono 1 (fijo):',
                'Teléfono 1 (fijo):',
                'Codigo Teléfono 2 (celular):',
                'Teléfono 2 (celular):',
                'Nombre de la persona contacto',
                'Apellido de la persona contacto',
                'Codigo Tipo ubicación ',
                'Tipo ubicación ',
                'Correo electrónico',
                'Observaciones',
                'RIF',
                'Cedula',
                'Edificio/Casa',
                'Detalle de Edificio/Casa',
                'Piso',
                'Detalle de Piso',
                'Local/Oficina',
                'Detalle de Local/Oficina',
                'Urbanización/Sector/Barrio',
                'Detalle de Urbanización/Sector/Barrio',
                'Tipo vía principal',
                'Vía principal ',
                'Tipo de vía 1',
                'Vía 1 ',
                'Tipo de vía 2',
                'Vía 2',
                'Referencia',
                'Detalle de Referencia',
                'Día de visita',
                'ZONA_FQ',
                'GDV_FQ',
                'OFIC_FQ',
                'CS_COD',
                'RUTA_FQ',
                'DESTINATARIO_FQ',
                'TERRITORIO_FQ',
                'DIVISION_FQ',
                'CI_coordinador_FQ'
            ];
        } elseif ($tableName === 'clientes_cambio_estatus_ep') {
            $headers = [
                'Feacha de creacion ',
                'Usuario',
                'Nombre de la distribuidora ',
                'Codigo cep ',
                'Cambio de Estatus',
                'Motivos del cambio de Estatus',
                'Nuevo codigo CEP',
                'CEP del mismo dueño',
                'CEP duplicado',
                'Fecha de suspensión (desde)',
                'Fecha de suspensión (hasta)',
                'CI_coordinador_FQ'
            ];
        } elseif ($tableName === 'clientes_actualizacion_ep') {
            $headers = [
                'Feacha de creacion ',
                'Usuario',
                'Nombre de la distribuidora ',
                'Codigo cep ',
                'Tipo de solicitud',
                'Codigo Teléfono 1 (fijo):',
                'Teléfono 1 (fijo):',
                'Codigo Teléfono 2 (celular):',
                'Teléfono 2 (celular):',
                'Nombre de la persona contacto',
                'Apellido de la persona contacto',
                'Correo electrónico',
                'Nombre comercial',
                'Codigo Exclusividad en bebidas',
                'Exclusividad en bebidas',
                'Codigo Presencia de consumo en sitio en el PDV',
                'Presencia de consumo en sitio en el PDV',
                'Codigo Segmento',
                ' Segmento',
                '¿El cliente pertenece a una cadena?',
                'cadena a la que pertenece el cliente',
                'Codigo Tipo de cliente',
                'Tipo de cliente',
                'Coordenada (X)',
                'Coordenada (Y)',
                'Día de visita propuesto',
                'Modelo propuesto',
                'motivo del cambio',
                'Edificio/Casa',
                'Detalle de Edificio/Casa',
                'Piso',
                'Detalle de Piso',
                'Local/Oficina',
                'Detalle de Local/Oficina',
                'Tipo vía principal',
                'Vía principal ',
                'Tipo de vía 1',
                'Vía 1 ',
                'Tipo de vía 2',
                'Vía 2',
                'Referencia',
                'Detalle de Referencia'
            ];
        }

        $csvContent = implode(',', $headers) . "\r\n";

        foreach ($rows as $row) {
            $escapedRow = array_map(function($value) {
                $valStr = (string)$value;
                if (strpos($valStr, ',') !== false || strpos($valStr, '"') !== false || strpos($valStr, "\n") !== false || strpos($valStr, "\r") !== false) {
                    return '"' . str_replace('"', '""', $valStr) . '"';
                }
                return $valStr;
            }, array_values($row));
            
            $csvContent .= implode(',', $escapedRow) . "\r\n";
        }

        return $csvContent;
    }

    private function resolveSegment(string $codeOrName, $segments): array
    {
        $codeOrName = trim($codeOrName);
        if (empty($codeOrName)) {
            return ['code' => '', 'name' => ''];
        }

        // Try lookup by code
        $seg = $segments->firstWhere('tp3_code', $codeOrName);
        if ($seg) {
            return ['code' => $seg->tp3_code, 'name' => $seg->tp3_name];
        }

        // Try lookup by name
        $seg = $segments->first(fn($item) => strcasecmp($item->tp3_name, $codeOrName) === 0);
        if ($seg) {
            return ['code' => $seg->tp3_code, 'name' => $seg->tp3_name];
        }

        return ['code' => $codeOrName, 'name' => ''];
    }

    private function resolveClientType(string $value, $clientTypes): array
    {
        $value = trim($value);
        if (empty($value)) {
            return ['code' => '', 'name' => ''];
        }

        if (str_contains($value, '|')) {
            $parts = explode('|', $value);
            return ['code' => trim($parts[0]), 'name' => trim($parts[1])];
        }

        // Try lookup by code
        $ct = $clientTypes->firstWhere('tp2_code', $value);
        if ($ct) {
            return ['code' => $ct->tp2_code, 'name' => $ct->tp2_name];
        }

        // Try lookup by name
        $ct = $clientTypes->first(fn($item) => strcasecmp($item->tp2_name, $value) === 0);
        if ($ct) {
            return ['code' => $ct->tp2_code, 'name' => $ct->tp2_name];
        }

        return ['code' => '', 'name' => $value];
    }

    public function executeAndUpload(?string $startDate = null, ?string $endDate = null): array
    {
        // Si no se especifican fechas, usar ayer (yesterday) por defecto
        if (empty($startDate)) {
            $startDate = now()->subDay()->format('Y-m-d');
        }
        if (empty($endDate)) {
            $endDate = now()->subDay()->format('Y-m-d');
        }

        $startDateParsed = Carbon::parse($startDate);
        $endDateParsed = Carbon::parse($endDate);

        $tables = [
            'clientes_nuevos_ep',
            'clientes_actualizacion_ep',
            'clientes_cambio_estatus_ep'
        ];

        $isLocal = config('app.env') === 'local';
        $dateStr = now()->format('Ymd_His');
        $generatedData = [];

        foreach ($tables as $table) {
            $allRows = $this->execute($table, $startDateParsed->format('Y-m-d'), $endDateParsed->format('Y-m-d'));
            $csvContent = $this->generateCsvContent($allRows, $table);

            if ($table === 'clientes_nuevos_ep') {
                $subfolder = 'Creacion_Clientes_FQ/Excel_FQ/';
                $filename = "CREACION_CLIENTE_FQ_{$dateStr}.csv";
            } elseif ($table === 'clientes_cambio_estatus_ep') {
                $subfolder = 'Cambio_Estatus_FQ/Excel_FQ/';
                $filename = "CAMBIO_ESTATUS_FQ_{$dateStr}.csv";
            } else {
                $subfolder = 'Data_Maestra_FQ/Excel_FQ/';
                $filename = "ACTUALIZACION_DATA_MAESTRA_FQ_{$dateStr}.csv";
            }

            $filePath = $subfolder . $filename;

            if ($isLocal) {
                $localFullPath = storage_path("ftp/{$subfolder}");
                if (!file_exists($localFullPath)) {
                    mkdir($localFullPath, 0777, true);
                }
                file_put_contents($localFullPath . $filename, $csvContent);
            } else {
                \Illuminate\Support\Facades\Storage::disk('sftp_solicitudes')->put($filePath, $csvContent);
            }

            $generatedData[] = [
                'table' => $table,
                'file' => $filePath,
                'count' => count($allRows),
            ];
        }

        return [
            'success' => true,
            'files' => $generatedData,
            'destination' => $isLocal ? 'Local Storage (storage/ftp)' : 'SFTP Polar (/out/manual)'
        ];
    }
}

