<?php

namespace Modules\Analytics\Actions;

use Modules\Analytics\DataTransferObjects\ReportFilterData;
use Modules\Analytics\Services\TenantConnectionService;
use Modules\Analytics\Models\External\ExtProduct;
use Illuminate\Support\Facades\DB;

class GetSalesByProductAction
{
    public function __construct(
        private TenantConnectionService $tenantService
    ) {}

    public function execute(ReportFilterData $filters): array
    {
        $clients = $this->tenantService->resolveClients($filters->client_ids, $filters->region_ids);

        $aggregated = [];

        $tenantResults = $this->tenantService->forEachTenant($clients, function ($client) use ($filters) {
            $query = DB::connection('tenant')
                ->table('ventas_detalle')
                ->select(
                    'idproducto',
                    'producto',
                    DB::raw('YEAR(fecha) as year'),
                    DB::raw('MONTH(fecha) as month'),
                    DB::raw('SUM(cantidad) as total_quantity'),
                    DB::raw('SUM(montodivisas) as total_amount_usd'),
                    DB::raw('SUM(cantidad * precioventa) as total_amount_bs')
                )
                ->where('eliminado', 0)
                ->whereBetween('fecha', [$filters->start_date, $filters->end_date]);

            // Filter by product SKUs if provided
            if (!empty($filters->product_skus)) {
                $productIds = ExtProduct::on('tenant')
                    ->whereIn('codigoSKU', $filters->product_skus)
                    ->pluck('idproducto')
                    ->toArray();

                if (!empty($productIds)) {
                    $query->whereIn('idproducto', $productIds);
                }
            }

            // Filter by routes if provided
            if (!empty($filters->routes)) {
                $query->whereIn('ruta', $filters->routes);
            }

            return $query
                ->groupBy('idproducto', 'producto', DB::raw('YEAR(fecha)'), DB::raw('MONTH(fecha)'))
                ->orderBy('year')
                ->orderBy('month')
                ->get()
                ->toArray();
        });

        // Aggregate results from all tenants
        foreach ($tenantResults['results'] as $tenantResult) {
            foreach ($tenantResult['data'] as $row) {
                $key = "{$row->idproducto}_{$row->year}_{$row->month}";

                if (!isset($aggregated[$key])) {
                    $aggregated[$key] = [
                        'product_id' => $row->idproducto,
                        'product_name' => $row->producto,
                        'year' => $row->year,
                        'month' => $row->month,
                        'total_quantity' => 0,
                        'total_amount_usd' => 0,
                        'total_amount_bs' => 0,
                    ];
                }

                $aggregated[$key]['total_quantity'] += $row->total_quantity;
                $aggregated[$key]['total_amount_usd'] += $row->total_amount_usd;
                $aggregated[$key]['total_amount_bs'] += $row->total_amount_bs;
            }
        }

        // Sort by year, month, then product name
        $sorted = collect(array_values($aggregated))
            ->sortBy([['year', 'asc'], ['month', 'asc'], ['product_name', 'asc']])
            ->values()
            ->toArray();

        return [
            'data' => $sorted,
            'clients_queried' => $clients->count(),
            'errors' => $tenantResults['errors'],
        ];
    }
}
