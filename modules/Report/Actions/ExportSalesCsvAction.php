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

    private const GENERIC_CEP_CODE = 'PV12345678';

    public array $errors = [];

    public function __construct(
        private TenantConnectionService $tenantService
    ) {}

    public function execute(ExportSalesCsvFilterData $filters): array
    {
        $this->errors = [];
        // 1. Resolver rutas: por code específico o todas las activas
        if ($filters->route_code) {
            $clients = CompanyRoute::where('is_active', true)
                ->where('code', $filters->route_code)
                ->get();
        } else {
            $clients = $this->tenantService->resolveClients();
        }

        $rows = [];
        $startDate = Carbon::parse($filters->start_date);
        $endDate = $filters->end_date ? Carbon::parse($filters->end_date) : null;
        $isRange = !is_null($endDate);
        
        $dayOfWeek = self::DAY_MAP[$startDate->dayOfWeek];

        // 2. Por cada tenant, obtener ventas + detalles + RJ
        $tenantResults = $this->tenantService->forEachTenant($clients, function ($client) use ($filters, $startDate, $endDate, $isRange, $dayOfWeek) {
            $routeCode = $client->code;
            $cep = $client->cep ?? '';
            $tenantRows = [];

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

            // EXCLUIR OBSEQUIOS: Si el IdVenta está en la tabla de planes tácticos, no es una venta normal
            if (Schema::connection('tenant')->hasTable('recepcion_plan_tactico')) {
                $obsequioIds = DB::connection('tenant')->table('recepcion_plan_tactico')->pluck('IdVenta')->toArray();
                if (!empty($obsequioIds)) {
                    $queryBase->whereNotIn('v.IdVenta', $obsequioIds);
                }
            }
            
            $countFiltros = (clone $queryBase)->count();
            Log::error("      [DETECTIVE] Cliente $routeCode - Tras Filtros Eliminado/Excluir Obsequios: $countFiltros registros.");

            // PASO 3: Filtro de Fecha
            if ($isRange) {
                $queryBase->whereBetween('v.Fecha', [$filters->start_date, $filters->end_date]);
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
                    'c.RIF',
                    'c.cep as client_cep'
                );

            if ($hasDistTable) {
                $queryBase->addSelect('lde.reaCode');
            }

            $salesData = $queryBase->get();

            foreach ($salesData as $row) {
                // UM: unidadesporcaja = 1 → UND, > 1 → CJS
                $um = ($row->unidadesporcaja && $row->unidadesporcaja > 1) ? 'CJS' : 'UND';

                // Cl. Doc: Siempre FVTA para este reporte (los obsequios van por separado)
                $clDoc = 'FVTA';

                $clientCep = !empty($row->client_cep) ? $row->client_cep : $row->IdCliente;
                if (strlen((string)$clientCep) !== 10) {
                    $clientCep = self::GENERIC_CEP_CODE;
                }

                $tenantRows[] = [
                    'fq_redi'       => $cep, // El CEP del Tenant va ahora en FQ/REDI
                    'cep'           => $clientCep,
                    'fecha'         => Carbon::parse($row->Fecha)->format('d-m-Y'),
                    'deudor'        => $row->IdCliente,
                    'doc_fq_redi'   => $row->IdVenta,
                    'material'      => $row->codigoSKU,
                    'cantidad'      => $row->cantidad,
                    'um'            => $um,
                    'rif_ci_clte'   => $row->RIF ?? '',
                    'cl_doc'        => $clDoc,
                    'motivo'        => $row->reaCode ?? '',
                ];
            }

            // Lógica RJ: Solo si NO es un rango (es un reporte diario específico)
            if (!$isRange) {
                $clientesConVenta = DB::connection('tenant')
                    ->table('ventaspxc')
                    ->where('eliminado', 0)
                    ->whereDate('Fecha', $filters->start_date)
                    ->pluck('IdCliente')
                    ->unique()
                    ->toArray();

                $clientesRJ = DB::connection('tenant')
                    ->table('clientes')
                    ->where('DiaDespacho1', $dayOfWeek)
                    ->whereNotIn('IdCliente', $clientesConVenta)
                    ->select('IdCliente', 'RIF', 'cep as client_cep')
                    ->get();

                foreach ($clientesRJ as $clienteRJ) {
                    $clientCepRJ = !empty($clienteRJ->client_cep) ? $clienteRJ->client_cep : $clienteRJ->IdCliente;
                    if (strlen((string)$clientCepRJ) !== 10) {
                        $clientCepRJ = self::GENERIC_CEP_CODE;
                    }

                    $tenantRows[] = [
                        'fq_redi'       => $cep,
                        'cep'           => $clientCepRJ,
                        'fecha'         => $startDate->format('d-m-Y'),
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
