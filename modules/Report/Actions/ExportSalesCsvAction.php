<?php

namespace Modules\Report\Actions;

use Carbon\Carbon;
use Modules\Report\DataTransferObjects\ExportSalesCsvFilterData;
use Modules\Analytics\Services\TenantConnectionService;
use Modules\CompanyRoute\Models\CompanyRoute;
use Illuminate\Support\Facades\DB;

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

    public function __construct(
        private TenantConnectionService $tenantService
    ) {}

    public function execute(ExportSalesCsvFilterData $filters): array
    {
        // 1. Resolver rutas: por code específico o todas las activas
        if ($filters->route_code) {
            $clients = CompanyRoute::where('is_active', true)
                ->where('code', $filters->route_code)
                ->get();
        } else {
            $clients = $this->tenantService->resolveClients();
        }

        $rows = [];
        $date = Carbon::parse($filters->date);
        $dayOfWeek = self::DAY_MAP[$date->dayOfWeek];

        // 2. Por cada tenant, obtener ventas + detalles + RJ
        $tenantResults = $this->tenantService->forEachTenant($clients, function ($client) use ($filters, $date, $dayOfWeek) {
            $routeCode = $client->code;
            $tenantRows = [];

            // Query principal: ventaspxc → ventas_detalle → productos → clientes
            $salesData = DB::connection('tenant')
                ->table('ventaspxc as v')
                ->join('ventas_detalle as vd', function ($join) {
                    $join->on('v.IdVenta', '=', 'vd.IdVenta')
                         ->where('vd.eliminado', '=', 0);
                })
                ->join('productos as p', 'vd.idproducto', '=', 'p.idproducto')
                ->leftJoin('clientes as c', 'v.IdCliente', '=', 'c.IdCliente')
                ->where('v.eliminado', 0)
                ->whereDate('v.Fecha', $filters->date)
                ->select(
                    'v.IdVenta',
                    'v.Fecha',
                    'v.IdCliente',
                    'vd.cantidad',
                    'p.codigoSKU',
                    'p.unidadesporcaja',
                    'c.RIF'
                )
                ->get();

            foreach ($salesData as $row) {
                // UM: unidadesporcaja = 1 → UND, > 1 → CJS
                $um = ($row->unidadesporcaja && $row->unidadesporcaja > 1) ? 'CJS' : 'UND';

                // Cl. Doc: si codigoSKU contiene 'OBS 13' o 'OBS 14' → OBSQ, sino → FVTA
                $clDoc = 'FVTA';
                if ($row->codigoSKU) {
                    $skuUpper = strtoupper($row->codigoSKU);
                    if (str_contains($skuUpper, 'OBS 13') || str_contains($skuUpper, 'OBS 14')) {
                        $clDoc = 'OBSQ';
                    }
                }

                $tenantRows[] = [
                    'fq_redi'       => $routeCode,
                    'fecha'         => $date->format('d.m.Y'),
                    'deudor'        => $row->IdCliente,
                    'doc_fq_redi'   => $row->IdVenta,
                    'material'      => $row->codigoSKU,
                    'cantidad'      => $row->cantidad,
                    'um'            => $um,
                    'rif_ci_clte'   => $row->RIF ?? '',
                    'cl_doc'        => $clDoc,
                    'motivo'        => '',
                ];
            }

            // Lógica RJ: clientes programados para ese día SIN venta
            $clientesConVenta = DB::connection('tenant')
                ->table('ventaspxc')
                ->where('eliminado', 0)
                ->whereDate('Fecha', $filters->date)
                ->pluck('IdCliente')
                ->unique()
                ->toArray();

            $clientesRJ = DB::connection('tenant')
                ->table('clientes')
                ->where('DiaDespacho1', $dayOfWeek)
                ->whereNotIn('IdCliente', $clientesConVenta)
                ->select('IdCliente', 'RIF')
                ->get();

            foreach ($clientesRJ as $clienteRJ) {
                $tenantRows[] = [
                    'fq_redi'       => $routeCode,
                    'fecha'         => $date->format('d.m.Y'),
                    'deudor'        => $clienteRJ->IdCliente,
                    'doc_fq_redi'   => '',
                    'material'      => '',
                    'cantidad'      => '',
                    'um'            => '',
                    'rif_ci_clte'   => $clienteRJ->RIF ?? '',
                    'cl_doc'        => '',
                    'motivo'        => 'RJ',
                ];
            }

            return $tenantRows;
        });

        // 3. Consolidar todas las filas
        foreach ($tenantResults['results'] as $tenantResult) {
            foreach ($tenantResult['data'] as $row) {
                $rows[] = $row;
            }
        }

        return [
            'rows' => $rows,
            'errors' => $tenantResults['errors'],
        ];
    }
}
