<?php

namespace Modules\CompanyRoute\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\CompanyRoute\Models\CompanyRoute;

/**
 * SyncRouteMetadataAction
 *
 * Enriquece la información de las rutas en el HUB (PolarAPI)
 * trayendo datos de dirección y sub-región desde la tabla companies_logins
 * del sistema origen (productosPolarApi).
 */
class SyncRouteMetadataAction
{
    public function execute(): array
    {
        Log::info("=== INICIO: Sincronización de Metadatos de Rutas (HUB -> Tenants) ===");

        $results = [
            'hub_updated' => 0,
            'tenants_processed' => 0,
            'errors' => [],
        ];

        try {
            // 1. Obtener todas las rutas registradas en el HUB (las oficiales con db_name)
            $routes = CompanyRoute::where('is_active', true)
                ->where('db_name', 'LIKE', 'www_v%p')
                ->get();

            // 2. Conectar a la base de datos origen
            $sourceDb = DB::connection('productos_polar');

            foreach ($routes as $route) {
                try {
                    // Match por nombre
                    $metadata = $sourceDb->table('companies_logins')
                        ->where('lgn_name', $route->name)
                        ->first();

                    if ($metadata) {
                        // Separar Zona de Venta de Dirección 1 (ej: "N016 Ocumare del Tuy")
                        $street1Parts = explode(' ', $metadata->lgn_street1, 2);
                        $saleZone = $street1Parts[0] ?? '';
                        $cleanStreet1 = $street1Parts[1] ?? $metadata->lgn_street1;

                        // Actualizar HUB
                        $route->update([
                            'address_street1' => $cleanStreet1,
                            'address_street2' => $metadata->lgn_street2,
                            'address_street3' => $metadata->lgn_street3,
                            'subregion_code'  => $metadata->srg_code,
                            'sale_zone'       => $saleZone,
                        ]);
                        $results['hub_updated']++;

                        // Empujar al Tenant
                        $this->pushToTenant($route);
                        $results['tenants_processed']++;
                        
                        Log::info("Metadatos sincronizados (Zona: {$saleZone}) para ruta: {$route->name}");
                    }

                } catch (\Exception $e) {
                    $results['errors'][] = "Error en ruta {$route->name}: " . $e->getMessage();
                    Log::error("Error sincronizando metadatos para {$route->name}: " . $e->getMessage());
                }
            }

        } catch (\Exception $e) {
            Log::error("Error general en SyncRouteMetadataAction: " . $e->getMessage());
            $results['errors'][] = $e->getMessage();
        }

        return $results;
    }

    private function pushToTenant(CompanyRoute $route): void
    {
        // Conectar al Tenant
        config(['database.connections.tenant.database' => $route->db_name]);
        DB::purge('tenant');

        // Asegurar tabla (Recrear para asegurar esquema actualizado)
        DB::connection('tenant')->statement("DROP TABLE IF EXISTS `sucursal_polar` ");
        DB::connection('tenant')->statement("
            CREATE TABLE `sucursal_polar` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `nombre` varchar(255) DEFAULT NULL,
                `direccion_1` varchar(255) DEFAULT NULL,
                `direccion_2` varchar(255) DEFAULT NULL,
                `direccion_3` varchar(255) DEFAULT NULL,
                `sub_region` varchar(100) DEFAULT NULL,
                `zona_venta` varchar(50) DEFAULT NULL,
                `synced_at` timestamp DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        // Insertar (Full Refresh)
        DB::connection('tenant')->table('sucursal_polar')->insert([
            'nombre'      => $route->name,
            'direccion_1' => $route->address_street1,
            'direccion_2' => $route->address_street2,
            'direccion_3' => $route->address_street3,
            'sub_region'  => $route->subregion_code,
            'zona_venta'  => $route->sale_zone,
            'synced_at'   => now(),
        ]);
    }
}
