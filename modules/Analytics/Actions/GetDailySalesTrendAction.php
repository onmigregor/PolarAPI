<?php

namespace Modules\Analytics\Actions;

use Modules\Analytics\DataTransferObjects\ReportFilterData;
use Modules\Analytics\Services\TenantConnectionService;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class GetDailySalesTrendAction
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
                    DB::raw('DATE(Fecha) as date'),
                    DB::raw('COUNT(*) as total_transactions'),
                    DB::raw('SUM(MontoFactura) as total_billed_bs'),
                    DB::raw('SUM(montodivisas) as total_billed_usd'),
                    DB::raw('SUM(MontoPendiente) as total_pending')
                )
                ->where('eliminado', 0)
                ->whereBetween('Fecha', [$filters->start_date, $filters->end_date]);

            if (!empty($filters->routes)) {
                $query->whereIn('Ruta', $filters->routes);
            }

            return $query
                ->groupBy(DB::raw('DATE(Fecha)'))
                ->orderBy('date')
                ->get()
                ->toArray();
        });

        // Aggregate results from all tenants
        foreach ($tenantResults['results'] as $tenantResult) {
            foreach ($tenantResult['data'] as $row) {
                $key = $row->date;

                if (!isset($aggregated[$key])) {
                    $aggregated[$key] = [
                        'date' => $row->date,
                        'total_transactions' => 0,
                        'total_billed_bs' => 0,
                        'total_billed_usd' => 0,
                        'total_pending' => 0,
                    ];
                }

                $aggregated[$key]['total_transactions'] += $row->total_transactions;
                $aggregated[$key]['total_billed_bs'] += (float) $row->total_billed_bs;
                $aggregated[$key]['total_billed_usd'] += (float) $row->total_billed_usd;
                $aggregated[$key]['total_pending'] += (float) $row->total_pending;
            }
        }

        // Fill in missing days with zeroes
        $startDate = Carbon::parse($filters->start_date);
        $endDate = Carbon::parse($filters->end_date);
        $period = CarbonPeriod::create($startDate, $endDate);

        $filled = [];
        foreach ($period as $date) {
            $key = $date->format('Y-m-d');
            $filled[] = $aggregated[$key] ?? [
                'date' => $key,
                'total_transactions' => 0,
                'total_billed_bs' => 0,
                'total_billed_usd' => 0,
                'total_pending' => 0,
            ];
        }

        return [
            'data' => $filled,
            'clients_queried' => $clients->count(),
            'errors' => $tenantResults['errors'],
        ];
    }
}
