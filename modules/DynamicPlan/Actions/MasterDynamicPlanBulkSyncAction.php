<?php

namespace Modules\DynamicPlan\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Modules\DynamicPlan\Models\MasterDynamicPlan;
use Modules\CompanyRoute\Models\CompanyRoute;

class MasterDynamicPlanBulkSyncAction
{
    private const CREATE_TENANT_TABLE_SQL = "
        CREATE TABLE IF NOT EXISTS `dynamics_plans` (
            `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            `cod_fq` varchar(255) DEFAULT NULL,
            `meta_cerveceria` double DEFAULT '0',
            `meta_maltin` double DEFAULT '0',
            `meta_sangria` double DEFAULT '0',
            `meta_pcv` double DEFAULT '0',
            `meta_apc` double DEFAULT '0',
            `meta_pomar` double DEFAULT '0',
            `metas_pg` double DEFAULT '0',
            `created_at` timestamp NULL DEFAULT NULL,
            `updated_at` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `idx_cod_fq` (`cod_fq`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    public function execute(array $data): array
    {
        // Log Especial de Inicio
        Log::channel('single')->info("=== INICIO DE SINCRONIZACIÓN DINÁMICA ===");
        Log::channel('single')->info("Registros recibidos del Admin: " . count($data));

        $results = [
            'master_updated' => 0,
            'tenants_processed' => 0,
            'pushed_to_tenants' => 0,
            'errors' => [],
        ];

        if (empty($data)) {
            Log::channel('single')->warning("La data recibida está vacía.");
            return $results;
        }

        $updateColumns = [
            'meta_cerveceria', 'meta_maltin', 'meta_sangria', 'meta_pcv', 'meta_apc', 'meta_pomar', 'metas_pg', 'updated_at'
        ];

        $syncData = array_map(function($item) {
            return [
                'cod_fq' => $item['cod_fq'],
                'meta_cerveceria' => $item['meta_cerveceria'] ?? 0,
                'meta_maltin' => $item['meta_maltin'] ?? 0,
                'meta_sangria' => $item['meta_sangria'] ?? 0,
                'meta_pcv' => $item['meta_pcv'] ?? 0,
                'meta_apc' => $item['meta_apc'] ?? 0,
                'meta_pomar' => $item['meta_pomar'] ?? 0,
                'metas_pg' => $item['metas_pg'] ?? 0,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }, $data);

        // 1. Guardar en Master
        try {
            MasterDynamicPlan::upsert($syncData, ['cod_fq'], $updateColumns);
            $results['master_updated'] = count($syncData);
            Log::channel('single')->info("Master Table: Upsert exitoso de " . count($syncData) . " registros.");
        } catch (\Exception $e) {
            Log::channel('single')->error("Error en Master Table: " . $e->getMessage());
            $results['errors'][] = "Master Error: " . $e->getMessage();
        }

        // 2. Obtener la data filtrada por CEP (Match con company_routes)
        $recordsWithTenants = DB::table('master_dynamic_plans as mdp')
            ->join('company_routes as routes', 'routes.cep', '=', 'mdp.cod_fq')
            ->select('mdp.*', 'routes.db_name')
            ->get()
            ->groupBy('db_name');
        
        Log::channel('single')->info("Grupos de planes por tenant encontrados: " . count($recordsWithTenants));

        // 3. Loop de Tenants y distribución filtrada
        foreach ($recordsWithTenants as $dbName => $records) {
            Log::channel('single')->info("Procesando Tenant: {$dbName} (" . count($records) . " registros)");

            try {
                Config::set('database.connections.tenant.database', $dbName);
                DB::purge('tenant');
                
                // A. Asegurar Tabla
                DB::connection('tenant')->statement(self::CREATE_TENANT_TABLE_SQL);

                // Limpiar data para el upsert (quitar db_name y el id de la master)
                $cleanRecords = array_map(function($item) {
                    $row = (array)$item;
                    unset($row['id']);
                    unset($row['db_name']);
                    return $row;
                }, $records->toArray());

                // B. UPSERT filtrado
                DB::connection('tenant')
                    ->table('dynamics_plans')
                    ->upsert($cleanRecords, ['cod_fq'], $updateColumns);

                $results['pushed_to_tenants'] += count($cleanRecords);
                $results['tenants_processed']++;
                
                Log::channel('single')->info("Tenant {$dbName}: Sincronización exitosa.");

            } catch (\Exception $e) {
                Log::channel('single')->error("Error en Tenant {$dbName}: " . $e->getMessage());
                $results['errors'][] = "Tenant {$dbName}: " . $e->getMessage();
            }
        }

        Log::channel('single')->info("=== FIN DE SINCRONIZACIÓN DINÁMICA ===");
        return $results;
    }
}
