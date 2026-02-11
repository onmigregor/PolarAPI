<?php

namespace Modules\Analytics\Actions;

use Modules\Analytics\DataTransferObjects\ReportFilterData;
use Modules\Analytics\Services\TenantConnectionService;
use Illuminate\Support\Facades\DB;

class GetSalesByRouteAction
{
    public function __construct(
        private TenantConnectionService $tenantService
    ) {}

    public function execute(ReportFilterData $filters): array
    {
        $clients = $this->tenantService->resolveClients($filters->client_ids);

        $aggregated = [];

        $tenantResults = $this->tenantService->forEachTenant($clients, function ($client) use ($filters) {
            $query = DB::connection('tenant')
                ->table('ventaspxc')
                ->select(
                    'Ruta as route',
                    DB::raw('COUNT(*) as total_transactions'),
                    DB::raw('SUM(MontoFactura) as total_billed_bs'),
                    DB::raw('SUM(montodivisas) as total_billed_usd')
                )
                ->where('eliminado', 0)
                ->whereBetween('Fecha', [$filters->start_date, $filters->end_date]);

            // Filter by routes if provided
            if (!empty($filters->routes)) {
                $query->whereIn('Ruta', $filters->routes);
            }

            return $query
                ->groupBy('Ruta')
                ->orderByDesc(DB::raw('SUM(montodivisas)'))
                ->get()
                ->toArray();
        });

        // Aggregate results from all tenants
        foreach ($tenantResults['results'] as $tenantResult) {
            $clientName = $tenantResult['client_name'];

            foreach ($tenantResult['data'] as $row) {
                $key = "{$clientName}_{$row->route}";

                if (!isset($aggregated[$key])) {
                    $aggregated[$key] = [
                        'client_name' => $clientName,
                        'route' => $row->route,
                        'total_transactions' => 0,
                        'total_billed_bs' => 0,
                        'total_billed_usd' => 0,
                    ];
                }

                $aggregated[$key]['total_transactions'] += $row->total_transactions;
                $aggregated[$key]['total_billed_bs'] += $row->total_billed_bs;
                $aggregated[$key]['total_billed_usd'] += $row->total_billed_usd;
            }
        }

        // Sort by total_billed_usd desc
        $sorted = collect(array_values($aggregated))
            ->sortByDesc('total_billed_usd')
            ->values()
            ->toArray();

        return [
            'data' => $sorted,
            'clients_queried' => $clients->count(),
            'errors' => $tenantResults['errors'],
        ];
    }
}
