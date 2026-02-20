<?php

namespace Modules\Analytics\Actions;

use Modules\Analytics\DataTransferObjects\ReportFilterData;
use Modules\Analytics\Services\TenantConnectionService;
use Modules\MasterGroup\Models\MasterGroup;
use Illuminate\Support\Facades\DB;

class GetTopGroupsByLitersAction
{
    public function __construct(
        private TenantConnectionService $tenantService
    ) {}

    public function execute(ReportFilterData $filters, int $limit = 15): array
    {
        $groupNames = MasterGroup::where('unit_type', 'L')
            ->where('is_active', true)
            ->pluck('name')
            ->toArray();

        if (empty($groupNames)) {
            return [
                'data' => [],
                'clients_queried' => 0,
                'errors' => [],
            ];
        }

        $clients = $this->tenantService->resolveClients($filters->client_ids, $filters->region_ids);
        $aggregated = [];

        $tenantResults = $this->tenantService->forEachTenant($clients, function ($client) use ($filters, $groupNames) {
            $placeholders = implode(',', array_fill(0, count($groupNames), '?'));

            $query = DB::connection('tenant')
                ->table('ventas_detalle as vd')
                ->join('productos as p', 'p.idproducto', '=', 'vd.idproducto')
                ->where('vd.eliminado', 0)
                ->whereBetween('vd.fecha', [$filters->start_date, $filters->end_date])
                ->whereRaw("TRIM(p.grupo) IN ({$placeholders})", $groupNames)
                ->where('p.unidadesporcaja', '>', 0)
                ->whereNotNull('p.litros')
                ->whereRaw("TRIM(COALESCE(p.litros, '')) <> ''")
                ->whereRaw(
                    "CAST(REPLACE(REPLACE(TRIM(p.litros), ',', '.'), ' ', '') AS DECIMAL(10,4)) > 0"
                )
                ->select(
                    'p.grupo as group_name',
                    DB::raw(
                        "SUM(vd.cantidad * p.unidadesporcaja * " .
                        "CAST(REPLACE(REPLACE(TRIM(p.litros), ',', '.'), ' ', '') AS DECIMAL(10,4))) as total_liters"
                    )
                )
                ->groupBy('p.grupo');

            if (!empty($filters->routes)) {
                $query->whereIn('vd.ruta', $filters->routes);
            }

            if (!empty($filters->product_skus)) {
                $query->whereIn('p.codigoSKU', $filters->product_skus);
            }

            return $query->get()->toArray();
        });

        foreach ($tenantResults['results'] as $tenantResult) {
            foreach ($tenantResult['data'] as $row) {
                $key = trim($row->group_name);
                if (!isset($aggregated[$key])) {
                    $aggregated[$key] = [
                        'group_name' => $key,
                        'total_liters' => 0.0,
                    ];
                }
                $aggregated[$key]['total_liters'] += (float) $row->total_liters;
            }
        }

        $sorted = collect(array_values($aggregated))
            ->sortByDesc('total_liters')
            ->take($limit)
            ->values()
            ->map(fn ($item) => [
                'group_name' => $item['group_name'],
                'total_liters' => round((float) $item['total_liters'], 2),
            ])
            ->toArray();

        return [
            'data' => $sorted,
            'clients_queried' => $clients->count(),
            'errors' => $tenantResults['errors'],
        ];
    }
}
