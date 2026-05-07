<?php

namespace Modules\MasterProduct\Actions;

use Modules\CompanyRoute\Models\CompanyRoute;
use Modules\MasterProduct\Models\MasterProduct;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class SyncMasterToClientsAction
{
    public function __construct(
        private SyncLookupTablesToClientsAction $syncLookupTables
    ) {}

    /**
     * Columnas a agregar/actualizar en los tenants.
     * Key: nombre de columna en la tabla `productos` del tenant.
     * Value: nombre de columna en master_products.
     */
    private const COLUMN_MAPPING = [
        'marca'     => 'brand',
        'familia'   => 'cl1_code',
        'categoria' => 'cl2_code',
        'grupo'     => 'cl3_code',
        'segmento'  => 'cl4_code',
        'unt_code'  => 'unt_code',
        'proshortname' => 'pro_short_name',
        'probarcode'   => 'barcode',
        'bomcode'      => 'pro_bom_code',
        'proreturnallowed' => 'pro_return_allowed',
        'prodamegereturnsallowed' => 'pro_damage_returns_allowed',
        'proavailableforsale' => 'pro_available_for_sale',
        'procustomerinventoryallowed' => 'pro_customer_inventory_allowed',
    ];

    public function execute(): array
    {
        // 1. Sincronizar tablas de búsqueda primero (familias, categorías, etc.)
        $lookupResults = $this->syncLookupTables->execute();

        $clients = CompanyRoute::where('is_active', true)->get();

        $results = [
            'clients_processed' => 0,
            'total_updated'     => 0,
            'total_skipped'     => 0,
            'total_unchanged'   => 0,
            'errors'            => [],
        ];

        // Cargar solo los productos maestros que tienen datos enriquecidos
        $masterProducts = MasterProduct::whereNotNull('cl2_code')
            ->orWhereNotNull('unt_code')
            ->get([
                'sku', 'brand', 'cl1_code', 'cl2_code', 'cl3_code', 'cl4_code', 'unt_code',
                'pro_short_name', 'barcode', 'pro_bom_code', 'pro_return_allowed',
                'pro_damage_returns_allowed', 'pro_available_for_sale', 'pro_customer_inventory_allowed'
            ])
            ->keyBy('sku');

        foreach ($clients as $client) {
            try {
                // Apuntar la conexión tenant a este cliente
                Config::set('database.connections.tenant.database', $client->db_name);
                DB::purge('tenant');
                DB::reconnect('tenant');

                // Asegurarse de que las columnas existen en la tabla del tenant
                $this->ensureColumnsExist($client->db_name);

                $updatedCount = 0;
                $skippedCount = 0;

                // Obtener todos los SKUs activos de este tenant junto con sus valores actuales
                $tenantProducts = DB::connection('tenant')
                    ->table('productos')
                    ->select(
                        'idproducto', 'codigoSKU', 'marca', 'familia', 'categoria', 'grupo', 'segmento', 'unt_code',
                        'proshortname', 'probarcode', 'bomcode', 'proreturnallowed', 
                        'prodamegereturnsallowed', 'proavailableforsale', 'procustomerinventoryallowed'
                    )
                    ->whereNotNull('codigoSKU')
                    ->where('codigoSKU', '<>', '')
                    ->get();

                foreach ($tenantProducts as $tenantProduct) {
                    $sku = $tenantProduct->codigoSKU;

                    if (!isset($masterProducts[$sku])) {
                        $skippedCount++;
                        continue;
                    }

                    $master = $masterProducts[$sku];

                    // Optimización Delta: Solo actualizar si hay cambios reales
                    if (
                        $tenantProduct->marca === $master->brand &&
                        $tenantProduct->familia === $master->cl1_code &&
                        $tenantProduct->categoria === $master->cl2_code &&
                        $tenantProduct->grupo === $master->cl3_code &&
                        $tenantProduct->segmento === $master->cl4_code &&
                        $tenantProduct->unt_code === $master->unt_code &&
                        $tenantProduct->proshortname === $master->pro_short_name &&
                        $tenantProduct->probarcode === $master->barcode &&
                        $tenantProduct->bomcode === $master->pro_bom_code &&
                        (int)$tenantProduct->proreturnallowed === (int)$master->pro_return_allowed &&
                        (int)$tenantProduct->prodamegereturnsallowed === (int)$master->pro_damage_returns_allowed &&
                        (int)$tenantProduct->proavailableforsale === (int)$master->pro_available_for_sale &&
                        (int)$tenantProduct->procustomerinventoryallowed === (int)$master->pro_customer_inventory_allowed
                    ) {
                        $results['total_unchanged'] = ($results['total_unchanged'] ?? 0) + 1;
                        continue;
                    }

                    DB::connection('tenant')
                        ->table('productos')
                        ->where('idproducto', $tenantProduct->idproducto)
                        ->update([
                            'marca'     => $master->brand,
                            'familia'   => $master->cl1_code,
                            'categoria' => $master->cl2_code,
                            'grupo'     => $master->cl3_code,
                            'segmento'  => $master->cl4_code,
                            'unt_code'  => $master->unt_code,
                            'proshortname' => $master->pro_short_name,
                            'probarcode'   => $master->barcode,
                            'bomcode'      => $master->pro_bom_code,
                            'proreturnallowed' => $master->pro_return_allowed,
                            'prodamegereturnsallowed' => $master->pro_damage_returns_allowed,
                            'proavailableforsale' => $master->pro_available_for_sale,
                            'procustomerinventoryallowed' => $master->pro_customer_inventory_allowed,
                        ]);

                    $updatedCount++;
                }

                $results['clients_processed']++;
                $results['total_updated'] += $updatedCount;
                $results['total_skipped'] += $skippedCount;

                DB::disconnect('tenant');

            } catch (\Exception $e) {
                $results['errors'][] = [
                    'client' => $client->name,
                    'error'  => $e->getMessage(),
                ];
                Log::error("Error en SyncMasterToClients para cliente {$client->name}: " . $e->getMessage());
            }
        }

        return $results;
    }

    private function ensureColumnsExist(string $dbName): void
    {
        $columns = [
            'marca'     => 'VARCHAR(255) NULL',
            'familia'   => 'VARCHAR(50) NULL',
            'categoria' => 'VARCHAR(100) NULL',
            'grupo'     => 'VARCHAR(100) NULL',
            'segmento'  => 'VARCHAR(100) NULL',
            'unt_code'  => 'VARCHAR(20) NULL',
            'proshortname' => 'VARCHAR(255) NULL',
            'probarcode'   => 'VARCHAR(50) NULL',
            'bomcode'      => 'VARCHAR(20) NULL',
            'proreturnallowed' => 'TINYINT(1) DEFAULT 0',
            'prodamegereturnsallowed' => 'TINYINT(1) DEFAULT 0',
            'proavailableforsale' => 'TINYINT(1) DEFAULT 1',
            'procustomerinventoryallowed' => 'TINYINT(1) DEFAULT 0',
        ];

        foreach ($columns as $column => $definition) {
            $columnExists = DB::connection('tenant')
                ->select("SHOW COLUMNS FROM `productos` LIKE '{$column}'");

            // Desactivar temporalmente el modo estricto para permitir ALTER con fechas 0000-00-00 existentes
            DB::connection('tenant')->statement("SET SESSION sql_mode = ''");

            if (empty($columnExists)) {
                DB::connection('tenant')->statement("ALTER TABLE `productos` ADD COLUMN `{$column}` {$definition}");
            } else {
                // Forzar que sea NULLable para evitar errores de integridad con datos del HUB
                DB::connection('tenant')->statement("ALTER TABLE `productos` MODIFY COLUMN `{$column}` {$definition}");
            }
        }
    }
}
