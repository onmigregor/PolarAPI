<?php

namespace Modules\Report\Actions;

use Carbon\Carbon;
use Modules\Report\DataTransferObjects\ExportSalesCsvFilterData;
use Modules\Analytics\Services\TenantConnectionService;
use Modules\CompanyRoute\Models\CompanyRoute;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ExportSalesCsvAction
{
    /**
     * Mapa de días de la semana en español (Carbon: 0=Sunday ... 6=Saturday).
     */
    private const DAY_MAP = [
        0 => 'DOMINGO',
        1 => 'LUNES',
        2 => 'MARTES',
        3 => 'MIERCOLES',
        4 => 'JUEVES',
        5 => 'VIERNES',
        6 => 'SABADO',
    ];

    private const DAY_COLUMN_MAP = [
        0 => 'ctr_sunday',
        1 => 'ctr_monday',
        2 => 'ctr_tuesday',
        3 => 'ctr_wednesday',
        4 => 'ctr_thursday',
        5 => 'ctr_friday',
        6 => 'ctr_saturday',
    ];


    public array $errors = [];

    public function __construct(
        private TenantConnectionService $tenantService
    ) {}

    public function execute(ExportSalesCsvFilterData $filters, string $table = 'company_routes'): array
    {
        $this->errors = [];
        
        // Obtener el mapeo de materiales con su untcode
        $materialsMap = DB::table('master_materiales')->pluck('untcode', 'material')->toArray();
        // 1. Resolver rutas: por code específico o todas las activas
        if ($filters->route_code) {
            $clients = CompanyRoute::from($table)->where('is_active', true)
                ->where('code', $filters->route_code)
                ->get();
        } else {
            $clients = $this->tenantService->resolveClients(null, $table);
        }

        $rows = [];
        $startDate = Carbon::parse($filters->start_date);
        $endDate = $filters->end_date ? Carbon::parse($filters->end_date) : null;
        $isRange = !is_null($endDate) && $startDate->format('Y-m-d') !== $endDate->format('Y-m-d');
        
        $dayOfWeek = self::DAY_MAP[$startDate->dayOfWeek];

        // 2. Por cada tenant, obtener ventas + detalles + RJ/PJ
        $tenantResults = $this->tenantService->forEachTenant($clients, function ($client) use ($filters, $startDate, $endDate, $isRange, $dayOfWeek, $materialsMap) {
            $routeCode = $client->code;
            $cep = $client->cep ?? '';
            $tenantRows = [];

            // Obtener cep_ocasional desde la tabla configuraciones del tenant
            $cepOcasional = null;
            try {
                $configVal = DB::connection('tenant')
                    ->table('configuraciones')
                    ->where('clave_configuracion', 'cep_ocasional')
                    ->value('valor_configuracion');

                if (!empty($configVal)) {
                    $cepOcasional = $configVal;
                }
            } catch (\Exception $e) {
                // Silently fallback if table/key is not found
            }

            // Buscar clientes planificados PMI para el día correspondiente en el Hub
            $dayColumn = self::DAY_COLUMN_MAP[$startDate->dayOfWeek];
            $routePrefix = strtoupper($client->route_name ?? '') . '-%';
            $pmiCustomers = DB::table('master_customer_routes')
                ->where('ctr_contact_person', 'like', $routePrefix)
                ->where($dayColumn, '1')
                ->where('prc_code_for_sale', 'like', 'PMI%')
                ->pluck('cus_code')
                ->map(fn($code) => ltrim((string)$code, '0'))
                ->toArray();

            // PASO 1: Join Base (Venta + Detalle + Producto)
            $queryBase = DB::connection('tenant')
                ->table('ventaspxc as v')
                ->join('ventas_detalle as vd', 'v.IdVenta', '=', 'vd.IdVenta')
                ->join('productos as p', 'vd.idproducto', '=', 'p.idproducto')
                ->leftJoin('clientes as c', 'v.IdCliente', '=', 'c.IdCliente');
            
            $countBase = (clone $queryBase)->count();
            Log::error("      [DETECTIVE] Cliente $routeCode - Join Base: $countBase registros.");

            // PASO 2: Filtros de Eliminado y Texto (Eliminados filtros de texto OBS por solicitud)
            $queryBase->where('vd.eliminado', 0)
                ->where('v.eliminado', 0);

            // EXCLUIR OBSEQUIOS: Si el IdVenta está en la tabla de seguimiento_cajas_promocion, no es una venta normal
            if (Schema::connection('tenant')->hasTable('seguimiento_cajas_promocion')) {
                $obsequioIds = DB::connection('tenant')
                    ->table('seguimiento_cajas_promocion')
                    ->where('status', 'entregado')
                    ->pluck('id_venta')
                    ->filter()
                    ->toArray();
                if (!empty($obsequioIds)) {
                    $queryBase->whereNotIn('v.IdVenta', $obsequioIds);
                }
            }
            
            $countFiltros = (clone $queryBase)->count();
            Log::error("      [DETECTIVE] Cliente $routeCode - Tras Filtros Eliminado/Excluir Obsequios: $countFiltros registros.");

            // PASO 3: Filtro de Fecha
            if ($isRange) {
                $queryBase->whereBetween('v.Fecha', [$filters->start_date . ' 00:00:00', $filters->end_date . ' 23:59:59']);
            } else {
                $queryBase->whereDate('v.Fecha', $filters->start_date);
            }

            $countFinal = (clone $queryBase)->count();
            Log::error("      [DETECTIVE] Cliente $routeCode - TRAS FILTRO FECHA: $countFinal registros.");

            // Left join con lotes_distribucion_entregas si la tabla existe
            $hasDistTable = Schema::connection('tenant')->hasTable('lotes_distribucion_entregas');
            if ($hasDistTable) {
                $queryBase->leftJoin('lotes_distribucion_entregas as lde', 'v.IdVenta', '=', 'lde.IdVenta');
            }

            $queryBase->select(
                    'v.IdVenta',
                    'v.Fecha',
                    'v.IdCliente',
                    'vd.cantidad',
                    'p.codigoSKU',
                    'p.producto',
                    'p.unidadesporcaja',
                    'p.class2',
                    'p.class3',
                    'c.RIF',
                    'c.cep as client_cep'
                );

            if ($hasDistTable) {
                $queryBase->addSelect('lde.reaCode');
            }

            $salesData = $queryBase->get();

            $processedNoSales = [];

            foreach ($salesData as $row) {
                $motivo = $row->reaCode ?? '';
                if (empty($motivo) && (float)$row->cantidad == 0 && in_array((string)$row->IdCliente, $pmiCustomers)) {
                    $motivo = 'PJ';
                }

                $hasNoSale = !empty($motivo);

                if ($hasNoSale) {
                    $noSaleKey = !empty($row->IdVenta) ? 'doc_' . $row->IdVenta : 'cli_' . $row->IdCliente . '_' . $row->Fecha;
                    if (isset($processedNoSales[$noSaleKey])) {
                        continue;
                    }
                    $processedNoSales[$noSaleKey] = true;
                }

                // UM: Buscar untcode correspondiente al SKU del material en el maestro. Si no existe, fallback a PZA.
                $sku = $row->codigoSKU ?? '';
                $um = $hasNoSale ? '' : ($materialsMap[$sku] ?? 'PZA');

                // Cl. Doc: Siempre FVTA para este reporte (los obsequios van por separado)
                $clDoc = $hasNoSale ? '' : 'FVTA';

                $clientCep = !empty($row->client_cep) ? $row->client_cep : $cepOcasional;
                if (!empty($clientCep)) {
                    $clientCep = str_pad((string)$clientCep, 10, '0', STR_PAD_LEFT);
                } else {
                    $clientCep = '';
                }

                $tenantRows[] = [
                    'fq_redi'       => $cep, // El CEP del Tenant va ahora en FQ/REDI
                    'cep'           => $clientCep,
                    'fecha'         => Carbon::parse($row->Fecha)->format('d.m.Y'), // Fecha con formato .
                    'deudor'        => $row->IdCliente,
                    'doc_fq_redi'   => $hasNoSale ? '' : $row->IdVenta,
                    'material'      => $hasNoSale ? '' : $row->codigoSKU,
                    'cantidad'      => $hasNoSale ? 0 : $row->cantidad,
                    'um'            => $um,
                    'rif_ci_clte'   => $row->RIF ?? '',
                    'cl_doc'        => $clDoc,
                    'motivo'        => $motivo,
                ];
            }

            // Lógica RJ y NV: Solo si NO es un rango (es un reporte diario específico)
            if (!$isRange) {
                $clientesConVenta = DB::connection('tenant')
                    ->table('ventaspxc as v')
                    ->join('clientes as c', 'v.IdCliente', '=', 'c.IdCliente')
                    ->where('v.eliminado', 0)
                    ->whereDate('v.Fecha', $filters->start_date)
                    ->pluck('c.cep')
                    ->unique()
                    ->map(fn($id) => ltrim((string)$id, '0'))
                    ->toArray();

                // 1. Agregar clientes PMI planificados para hoy que NO tuvieron ventas como "PJ"
                $clientesPmiSinVenta = array_diff($pmiCustomers, $clientesConVenta);
                if (!empty($clientesPmiSinVenta)) {
                    $clientesNV = DB::connection('tenant')
                        ->table('clientes')
                        ->whereIn('cep', $clientesPmiSinVenta)
                        ->select('IdCliente', 'RIF', 'cep as client_cep')
                        ->get();

                    foreach ($clientesNV as $clienteNV) {
                        $clientCepNV = !empty($clienteNV->client_cep) ? $clienteNV->client_cep : $cepOcasional;
                        if (!empty($clientCepNV)) {
                            $clientCepNV = str_pad((string)$clientCepNV, 10, '0', STR_PAD_LEFT);
                        } else {
                            $clientCepNV = '';
                        }

                        $tenantRows[] = [
                            'fq_redi'       => $cep,
                            'cep'           => $clientCepNV,
                            'fecha'         => $startDate->format('d.m.Y'), // Fecha con formato .
                            'deudor'        => $clienteNV->IdCliente,
                            'doc_fq_redi'   => '',
                            'material'      => '',
                            'cantidad'      => 0,
                            'um'            => '',
                            'rif_ci_clte'   => $clienteNV->RIF ?? '',
                            'cl_doc'        => '',
                            'motivo'        => 'PJ',
                        ];
                    }
                }

                // 2. Agregar clientes que corresponden al día de despacho como "RJ" (excluyendo a los clientes PMI ya procesados)
                $clientesRJ = DB::connection('tenant')
                    ->table('clientes')
                    ->where('DiaDespacho1', $dayOfWeek)
                    ->whereNotIn('cep', $clientesConVenta)
                    ->whereNotIn('cep', $pmiCustomers) // Excluir PMI de RJ para evitar duplicados
                    ->select('IdCliente', 'RIF', 'cep as client_cep')
                    ->get();

                foreach ($clientesRJ as $clienteRJ) {
                    $clientCepRJ = !empty($clienteRJ->client_cep) ? $clienteRJ->client_cep : $cepOcasional;
                    if (!empty($clientCepRJ)) {
                        $clientCepRJ = str_pad((string)$clientCepRJ, 10, '0', STR_PAD_LEFT);
                    } else {
                        $clientCepRJ = '';
                    }

                    $tenantRows[] = [
                        'fq_redi'       => $cep,
                        'cep'           => $clientCepRJ,
                        'fecha'         => $startDate->format('d.m.Y'), // Fecha con formato .
                        'deudor'        => $clienteRJ->IdCliente,
                        'doc_fq_redi'   => '',
                        'material'      => '',
                        'cantidad'      => 0,
                        'um'            => '',
                        'rif_ci_clte'   => $clienteRJ->RIF ?? '',
                        'cl_doc'        => '',
                        'motivo'        => 'RJ',
                    ];
                }
            }

            return $tenantRows;
        });

        // 3. Consolidar todas las filas
        $this->errors = $tenantResults['errors'];
        $allRows = [];
        foreach ($tenantResults['results'] as $tenantResult) {
            if (!empty($tenantResult['data'])) {
                $allRows = array_merge($allRows, $tenantResult['data']);
            }
        }

        return $allRows;
    }
}
