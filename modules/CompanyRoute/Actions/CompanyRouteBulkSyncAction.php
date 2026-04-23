<?php

namespace Modules\CompanyRoute\Actions;

use Modules\CompanyRoute\Models\CompanyRoute;
use Illuminate\Support\Facades\Log;

class CompanyRouteBulkSyncAction
{
    protected $initTenantAction;

    public function __construct(InitializeTenantDatabaseAction $initTenantAction)
    {
        $this->initTenantAction = $initTenantAction;
    }

    /**
     * Sincroniza múltiples rutas de empresa de forma masiva.
     */
    public function execute(array $data): array
    {
        $results = [
            'created' => 0,
            'updated' => 0,
            'tenants_initialized' => 0,
            'errors' => []
        ];

        foreach ($data as $item) {
            Log::info("Processing sync for CEP: " . $item['cep']);
            try {
                // 1. Upsert en la tabla maestra de rutas
                $route = CompanyRoute::updateOrCreate(
                    ['cep' => $item['cep']],
                    [
                        'code' => $item['code'] ?? null,
                        'name' => $item['name'] ?? null,
                        'route_name' => $item['route_name'] ?? null,
                        'db_name' => $item['db_name'] ?? null,
                        'is_active' => true,
                        'is_available_to_sync' => true,
                    ]
                );

                if ($route->wasRecentlyCreated) {
                    Log::info("Created new route for CEP: " . $item['cep']);
                    $results['created']++;
                } else {
                    Log::info("Updated existing route for CEP: " . $item['cep']);
                    $results['updated']++;
                }

                // 2. Inicializar Infraestructura de Tenant (BD + Tablas)
                if ($route->db_name) {
                    $initSuccess = $this->initTenantAction->execute($route->db_name);
                    if ($initSuccess) {
                        $results['tenants_initialized']++;
                    } else {
                        $results['errors'][] = "CEP {$item['cep']}: Failed to initialize database {$route->db_name}";
                    }
                }

            } catch (\Exception $e) {
                Log::error("Error syncing company route {$item['cep']}: " . $e->getMessage());
                $results['errors'][] = "CEP {$item['cep']}: " . $e->getMessage();
            }
        }

        return $results;
    }
}
