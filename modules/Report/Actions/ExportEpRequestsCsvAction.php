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

        // 1. Obtener datos de la BD central (logins y territorios)
        // Para cruzar por try_code (ruta)
        $territories = DB::table('master_company_territories')->get()->keyBy('try_code');
        $logins = DB::table('master_company_logins')->get()->keyBy('lgn_code');

        $allRows = [];

        $tenantResults = $this->tenantService->forEachTenant($clients, function ($client) use ($tableName, $territories, $logins, $startDate, $endDate) {
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
            $ciCoordinadorFq = ''; // CREWLOGIN_CRWCODE (No disponible en el esquema validado, se deja vacio)

            foreach ($epData as $row) {
                // Parse payload_json
                $payload = json_decode($row->payload_json ?? '{}', true);
                if (isset($payload['all_post'])) {
                    $payload = $payload['all_post']; // clientes_nuevos y clientes_cambio_estatus lo anidan
                }

                $tipoDocumento = $payload['tipoDocumentoFiscal'] ?? '';
                $numDocumento = $payload['numeroDocumentoFiscal'] ?? ($row->rif_fq ?? ($row->rif ?? ''));
                $cedula = '';
                $rif = '';
                if (strtoupper($tipoDocumento) === 'V' || strtoupper($tipoDocumento) === 'E' || strpos($numDocumento, 'V-') === 0 || strpos($numDocumento, 'E-') === 0) {
                    $cedula = $numDocumento;
                } else {
                    $rif = $numDocumento;
                }

                // Generar array de 60 columnas
                $tenantRows[] = [
                    'Feacha de creacion' => Carbon::parse($row->created_at)->format('m/d/Y'), // 1
                    'Usuario' => $row->usuario_id ?? '', // 2
                    'Nombre de la distribuidora' => $row->usuario_nombre ?? '', // 3
                    '¿Esta solicitud de creación es por un cambio de razón social?' => strtoupper($payload['cambioRazonSocial'] ?? 'NO'), // 4
                    'Codigo cep' => $row->codigo_cep_interno ?? ($row->cliente_id ?? ''), // 5
                    'Coordenada (X)' => str_replace('.', ',', (string)($row->longitud ?? ($payload['coordenadaX'] ?? ''))), // 6
                    'Coordenada (Y)' => str_replace('.', ',', (string)($row->latitud ?? ($payload['coordenadaY'] ?? ''))), // 7
                    'Indique el portafolio que desea comercializar' => is_array($payload['portafolio'] ?? null) ? implode(', ', $payload['portafolio']) : ($row->portafolio_fq ?? ''), // 8
                    'Codigo Exclusividad en bebidas' => '', // 9
                    'Exclusividad en bebidas' => $payload['exclusividadBebidas'] ?? ($row->exclusividad_fq ?? ''), // 10
                    'Codigo Presencia de consumo en sitio en el PDV' => '', // 11
                    'Presencia de consumo en sitio en el PDV' => $payload['consumoSitioPdv'] ?? ($row->consumo_sitio_fq ?? ''), // 12
                    'Codigo Segmento' => $payload['segmentoCliente'] ?? ($row->segmento_fq ?? ''), // 13
                    'Segmento' => '', // 14
                    '¿El cliente pertenece a una cadena?' => strtoupper($payload['perteneceCadena'] ?? ($row->cliente_pertenece_cadena_fq ?? 'NO')), // 15
                    'cadena a la que pertenece el cliente' => $payload['cadenaCliente'] ?? ($row->cadenas_fq ?? ''), // 16
                    'Codigo Tipo de cliente' => explode('|', $payload['tipoCliente'] ?? '')[0] ?? '', // 17
                    'Tipo de cliente' => explode('|', $payload['tipoCliente'] ?? '')[1] ?? ($row->tipo_cliente_fq ?? ''), // 18
                    'Nombre comercial' => $payload['nombreComercial'] ?? ($row->nombre_comercial_fq ?? ($row->cliente_nombre ?? '')), // 19
                    'Codigo Teléfono 1 (fijo):' => $payload['codigoTelefonoFijo'] ?? ($row->telefono_1_fq_codigo ?? ''), // 20
                    'Teléfono 1 (fijo):' => $payload['telefonoFijo'] ?? ($row->telefono_1_fq ?? ''), // 21
                    'Codigo Teléfono 2 (celular):' => $payload['codigoTelefonoMovil'] ?? ($row->telefono_2_fq_codigo ?? ''), // 22
                    'Teléfono 2 (celular):' => $payload['telefonoMovil'] ?? ($row->telefono_2_fq ?? ''), // 23
                    'Nombre de la persona contacto' => $payload['nombreContacto'] ?? ($row->persona_contacto_fq ?? ''), // 24
                    'Apellido de la persona contacto' => $payload['apellidoContacto'] ?? ($row->apellido_contacto_fq ?? ''), // 25
                    'Codigo Tipo ubicación' => $payload['tipoUbicacion'] ?? ($row->tipo_ubicacion_fq ?? ''), // 26
                    'Tipo ubicación' => '', // 27
                    'Correo electrónico' => $payload['correoPdv'] ?? ($row->correo_fq ?? ''), // 28
                    'Observaciones' => $payload['observacionesCliente'] ?? ($payload['cambioMotivo'] ?? ($row->motivo_cambio_estatus ?? '')), // 29
                    'RIF' => $rif, // 30
                    'Cedula' => $cedula, // 31
                    'Edificio/Casa' => $payload['direccionEdificioCasa'] ?? ($row->edificio_casa_fq ?? ''), // 32
                    'Detalle de Edificio/Casa' => $payload['detalleEdificioCasa'] ?? ($row->nombre_edif_fq ?? ''), // 33
                    'Piso' => $payload['direccionPiso'] ?? ($row->piso_fq ?? ''), // 34
                    'Detalle de Piso' => $payload['detallePiso'] ?? ($row->nombre_piso_fq ?? ''), // 35
                    'Local/Oficina' => $payload['direccionLocalOficina'] ?? ($row->local_oficina_fq ?? ''), // 36
                    'Detalle de Local/Oficina' => $payload['detalleLocalOficina'] ?? ($row->nombre_local_fq ?? ''), // 37
                    'Urbanización/Sector/Barrio' => $payload['direccionUrbanizacion'] ?? ($row->urb_sector_barrio_fq ?? ''), // 38
                    'Detalle de Urbanización/Sector/Barrio' => $payload['detalleUrbanizacion'] ?? ($row->nombre_urbanizacion_fq ?? ''), // 39
                    'Tipo vía principal' => $payload['tipoViaPrincipal'] ?? ($row->tipo_via_ppal_fq ?? ''), // 40
                    'Vía principal' => $payload['viaPrincipalTexto'] ?? ($row->via_ppal_fq ?? ''), // 41
                    'Tipo de vía 1' => $payload['tipoVia1'] ?? ($row->tipo_via_via1_fq ?? ''), // 42
                    'Vía 1' => $payload['via1Texto'] ?? ($row->via_1_fq ?? ''), // 43
                    'Tipo de vía 2' => $payload['tipoVia2'] ?? ($row->tipo_via_via2_fq ?? ''), // 44
                    'Vía 2' => $payload['via2Texto'] ?? ($row->via_2_fq ?? ''), // 45
                    'Referencia' => $payload['direccionReferencia'] ?? ($row->referencia_fq ?? ''), // 46
                    'Detalle de Referencia' => $payload['direccionReferenciaDetalle'] ?? ($row->nombre_referencia_fq ?? ''), // 47
                    'Día de visita' => is_array($payload['diasVisita'] ?? null) ? implode(', ', $payload['diasVisita']) : ($row->dia_visita ?? ''), // 48
                    'ZONA_FQ' => $zonaFq, // 49
                    'GDV_FQ' => $gdvFq, // 50
                    'OFIC_FQ' => $oficFq, // 51
                    'CS_COD' => $csCod, // 52
                    'RUTA_FQ' => $rutaFq, // 53
                    'DESTINATARIO_FQ' => '', // 54 (Vacío por solicitud)
                    'TERRITORIO_FQ' => $territorioFq, // 55
                    'DIVISION_FQ' => '', // 56 (Vacío por solicitud)
                    'CI_coordinador_FQ' => $ciCoordinadorFq, // 57
                ];
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

    public function generateCsvContent(array $rows): string
    {
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

        $csvContent = implode(',', $headers) . "\r\n";

        foreach ($rows as $row) {
            // Escape values
            $escapedRow = array_map(function($value) {
                if (strpos($value, ',') !== false || strpos($value, '"') !== false) {
                    return '"' . str_replace('"', '""', $value) . '"';
                }
                return $value;
            }, array_values($row));
            
            $csvContent .= implode(',', $escapedRow) . "\r\n";
        }

        return $csvContent;
    }
}
