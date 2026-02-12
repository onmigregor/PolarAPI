<?php

namespace Modules\Analytics\Services;

use Modules\CompanyRoute\Models\CompanyRoute;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class TenantConnectionService
{
    /**
     * Get the list of clients to query based on filter criteria.
     * If no client_ids provided, returns all active clients.
     */
    public function resolveClients(?array $clientIds = null): Collection
    {
        if (empty($clientIds)) {
            return CompanyRoute::where('is_active', true)->get();
        }

        return CompanyRoute::whereIn('id', $clientIds)
            ->where('is_active', true)
            ->get();
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
