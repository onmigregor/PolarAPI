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
        $clients = $this->tenantService->resolveClients($filters);

        $aggregated = [];

        $tenantResults = $this->tenantService->forEachTenant($clients, function ($client) use ($filters) {
            $query = DB::connection('tenant')
                ->table('ventas_detalle as vd')
                ->select(
                    'vd.idproducto',
                    'vd.producto',
                    DB::raw('SUM(vd.cantidad) as total_quantity'),
                    DB::raw('SUM(vd.montodivisas) as total_amount_usd'),
                    DB::raw('SUM(vd.cantidad * vd.precioventa) as total_amount_bs')
                )
                ->where('vd.eliminado', 0)
                ->whereBetween('vd.fecha', [$filters->start_date, $filters->end_date]);

            // Filter by product SKUs if provided or hierarchical filters
            $skusToFilter = $this->tenantService->resolveProductSkus($filters);

            if ($skusToFilter !== null) {
                // If skusToFilter is empty array, it means hierarchical filter yielded 0 results
                if (empty($skusToFilter)) {
                    // Force an empty result for this tenant by searching for an impossible ID
                    $query->where('vd.idproducto', -1);
                } else {
                    $productIds = ExtProduct::on('tenant')
                        ->whereIn('codigoSKU', $skusToFilter)
                        ->pluck('idproducto')
                        ->toArray();

                    if (!empty($productIds)) {
                        $query->whereIn('vd.idproducto', $productIds);
                    } else {
                        // SKUs matched hierarchy but tenant doesn't have these products active
                        $query->where('vd.idproducto', -1);
                    }
                }
            }

            // Filter by actual clients if provided (JOIN with ventaspxc since ventas_detalle has no client column)
            if (!empty($filters->client_ids)) {
                $query->join('ventaspxc as v', 'v.IdVenta', '=', 'vd.IdVenta')
                      ->whereIn('v.IdCliente', $filters->client_ids);
            }

            return $query
                ->groupBy('vd.idproducto', 'vd.producto')
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
