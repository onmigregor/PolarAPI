<?php

namespace Modules\Analytics\Actions;

use Modules\Analytics\DataTransferObjects\ReportFilterData;
use Modules\Analytics\Services\TenantConnectionService;
use Modules\Analytics\Models\External\ExtProduct;
use Illuminate\Support\Facades\DB;

class GetTopProductsAction
{
    public function __construct(
        private TenantConnectionService $tenantService
    ) {}

    public function execute(ReportFilterData $filters, int $limit = 10): array
    {
        $clients = $this->tenantService->resolveClients($filters->client_ids, $filters->region_ids);

        $aggregated = [];

        $tenantResults = $this->tenantService->forEachTenant($clients, function ($client) use ($filters) {
            $query = DB::connection('tenant')
                ->table('ventas_detalle')
                ->select(
                    'idproducto',
                    'producto',
                    DB::raw('SUM(cantidad) as total_quantity'),
                    DB::raw('SUM(montodivisas) as total_amount_usd'),
                    DB::raw('SUM(cantidad * precioventa) as total_amount_bs')
                )
                ->where('eliminado', 0)
                ->whereBetween('fecha', [$filters->start_date, $filters->end_date]);

            // Filter by product SKUs if provided or hierarchical filters
            $skusToFilter = $this->tenantService->resolveProductSkus($filters);

            if ($skusToFilter !== null) {
                // If skusToFilter is empty array, it means hierarchical filter yielded 0 results
                if (empty($skusToFilter)) {
                    // Force an empty result for this tenant by searching for an impossible ID
                    $query->where('idproducto', -1);
                } else {
                    $productIds = ExtProduct::on('tenant')
                        ->whereIn('codigoSKU', $skusToFilter)
                        ->pluck('idproducto')
                        ->toArray();

                    if (!empty($productIds)) {
                        $query->whereIn('idproducto', $productIds);
                    } else {
                        // SKUs matched hierarchy but tenant doesn't have these products active
                        $query->where('idproducto', -1);
                    }
                }
            }

            // Filter by routes if provided
            if (!empty($filters->routes)) {
                $query->whereIn('ruta', $filters->routes);
            }

            return $query
                ->groupBy('idproducto', 'producto')
                ->get()
                ->toArray();
        });

        // Aggregate results from all tenants
        foreach ($tenantResults['results'] as $tenantResult) {
            foreach ($tenantResult['data'] as $row) {
                $key = $row->idproducto;

                if (!isset($aggregated[$key])) {
                    $aggregated[$key] = [
                        'product_id' => $row->idproducto,
                        'product_name' => $row->producto,
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

        // Sort by total quantity desc and take top N
        $sorted = collect(array_values($aggregated))
            ->sortByDesc('total_quantity')
            ->take($limit)
            ->values()
            ->toArray();

        return [
            'data' => $sorted,
            'clients_queried' => $clients->count(),
            'errors' => $tenantResults['errors'],
        ];
    }
}
