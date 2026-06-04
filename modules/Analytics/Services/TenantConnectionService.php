<?php

namespace Modules\Analytics\Services;

use Modules\CompanyRoute\Models\CompanyRoute;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

use Modules\MasterProduct\Models\MasterProduct;
use Modules\Analytics\DataTransferObjects\ReportFilterData;

class TenantConnectionService
{
    /**
     * Get the list of clients to query based on filter criteria.
     * If no client_ids provided, returns all active clients.
     */
    public function resolveClients(?array $routeIds = null, ?array $regionIds = null): Collection
    {
        return CompanyRoute::where('is_active', true)
            ->when(!empty($routeIds), function ($query) use ($routeIds) {
                return $query->whereIn('id', $routeIds);
            })
            ->when(!empty($regionIds) && empty($routeIds), function ($query) use ($regionIds) {
                return $query->whereIn('region_id', $regionIds);
            })
            ->get();
    }

    /**
     * Resolve product SKUs taking into account hierarchical filters.
     * Returns an array of SKUs to filter by, or null if no product filter is applied.
     */
    public function resolveProductSkus(ReportFilterData $filters): ?array
    {
        $hasHierarchyFilters = !empty($filters->cl1_codes) || 
                               !empty($filters->cl2_codes) || 
                               !empty($filters->brand_codes) || 
                               !empty($filters->segment_codes);
                               
        $skusToFilter = $filters->product_skus;

        if ($hasHierarchyFilters) {
            $masterQuery = MasterProduct::query()->where('is_active', true);
            
            if (!empty($filters->cl1_codes)) {
                $masterQuery->whereIn('cl1_code', $filters->cl1_codes);
            }
            if (!empty($filters->cl2_codes)) {
                $masterQuery->whereIn('cl2_code', $filters->cl2_codes);
            }
            if (!empty($filters->brand_codes)) {
                $masterQuery->whereIn('brand_code', $filters->brand_codes);
            }
            if (!empty($filters->segment_codes)) {
                $masterQuery->whereIn('segment_code', $filters->segment_codes);
            }

            $hierarchySkus = $masterQuery->pluck('sku')->toArray();

            if (is_array($skusToFilter) && !empty($skusToFilter)) {
                // Intersect selected products with hierarchy results
                $skusToFilter = array_intersect($skusToFilter, $hierarchySkus);
            } else {
                // Only hierarchy filters
                $skusToFilter = $hierarchySkus;
            }
        }

        return $skusToFilter;
    }

    /**
     * Connect to a tenant's database using the client's db_name.
     */
    public function connect(CompanyRoute $client): void
    {
        Config::set('database.connections.tenant', [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => $client->db_name,
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
        ]);

        DB::purge('tenant');
    }

    /**
     * Disconnect from the current tenant database.
     */
    public function disconnect(): void
    {
        DB::purge('tenant');
    }

    /**
     * Execute a callback for each tenant, accumulating results.
     *
     * @param Collection $clients
     * @param callable $callback  Receives the Client instance
     * @return array ['results' => [...], 'errors' => [...]]
     */
    public function forEachTenant(Collection $clients, callable $callback): array
    {
        $results = [];
        $errors = [];

        foreach ($clients as $client) {
            try {
                $this->connect($client);
                $tenantResult = $callback($client);
                $results[] = [
                    'client_id' => $client->id,
                    'client_name' => $client->name,
                    'data' => $tenantResult,
                ];
            } catch (\Exception $e) {
                $errors[] = [
                    'client' => $client->name,
                    'error' => $e->getMessage(),
                ];
            } finally {
                $this->disconnect();
            }
        }

        return [
            'results' => $results,
            'errors' => $errors,
        ];
    }
}
