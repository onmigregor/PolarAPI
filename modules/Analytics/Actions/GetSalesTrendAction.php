<?php

namespace Modules\Analytics\Actions;

use Modules\Analytics\DataTransferObjects\ReportFilterData;
use Modules\Analytics\Services\TenantConnectionService;
use Illuminate\Support\Facades\DB;

class GetSalesTrendAction
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
                    DB::raw('YEAR(Fecha) as year'),
                    DB::raw('MONTH(Fecha) as month'),
                    DB::raw('COUNT(*) as total_transactions'),
                    DB::raw('SUM(MontoFactura) as total_billed_bs'),
                    DB::raw('SUM(montodivisas) as total_billed_usd'),
                    DB::raw('SUM(MontoPendiente) as total_pending')
                )
                ->where('eliminado', 0)
                ->whereBetween('Fecha', [$filters->start_date, $filters->end_date]);

            // Filter by routes if provided
            if (!empty($filters->routes)) {
                $query->whereIn('Ruta', $filters->routes);
            }

            return $query
                ->groupBy(DB::raw('YEAR(Fecha)'), DB::raw('MONTH(Fecha)'))
                ->orderBy('year')
                ->orderBy('month')
                ->get()
                ->toArray();
        });

        // Aggregate results from all tenants
        foreach ($tenantResults['results'] as $tenantResult) {
            foreach ($tenantResult['data'] as $row) {
                $key = "{$row->year}_{$row->month}";

                if (!isset($aggregated[$key])) {
                    $aggregated[$key] = [
                        'year' => $row->year,
                        'month' => $row->month,
                        'total_transactions' => 0,
                        'total_billed_bs' => 0,
                        'total_billed_usd' => 0,
                        'total_pending' => 0,
                    ];
                }

                $aggregated[$key]['total_transactions'] += $row->total_transactions;
                $aggregated[$key]['total_billed_bs'] += $row->total_billed_bs;
                $aggregated[$key]['total_billed_usd'] += $row->total_billed_usd;
                $aggregated[$key]['total_pending'] += $row->total_pending;
            }
        }

        // Sort by year, month
        $sorted = collect(array_values($aggregated))
            ->sortBy([['year', 'asc'], ['month', 'asc']])
            ->values()
            ->toArray();

        return [
            'data' => $sorted,
            'clients_queried' => $clients->count(),
            'errors' => $tenantResults['errors'],
        ];
    }
}
