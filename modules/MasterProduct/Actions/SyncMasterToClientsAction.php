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
        'class1'    => 'cl1_code',
        'class2'    => 'cl2_code',
        'class3'    => 'cl3_code',
        'class4'    => 'cl4_code',
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

        // 2. Definir valores por defecto para campos legacy obligatorios del Tenant
        $legacyDefaults = [
            'ruta'               => 'S/R',
            'descripcion1'       => '',
            'descripcion2'       => '',
            'imagen'             => 'no-image.png',
            'er'                 => 0,
            'tipo'               => 'PRODUCTO',
            'unidadesporcaja'    => 1,
            'montoganancia'      => 0,
            'porcentajeganancia' => 0,
            'fechaprecio'        => now()->format('Y-m-d'),
            'baseimponible'      => 0,
            'iva'                => 0,
            'excento_iva'        => 0,
            'grupo_precio'       => 'GENERAL',
            'producto_destacado' => 0,
            'producto_destacado2'=> 0,
            'producto_en_promocion'=> 0,
            'producto_activo'    => 1,
            'codigobarras'       => '',
            'textvoice'          => '',
            'graficar'           => 1,
        ];

        // 3. Cargar todos los productos maestros que tienen datos enriquecidos
        $masterProducts = MasterProduct::whereNotNull('cl2_code')
            ->orWhereNotNull('unt_code')
            ->get([
                'sku', 'name', 'brand', 'cl1_code', 'cl2_code', 'cl3_code', 'cl4_code', 'unt_code',
                'pro_short_name', 'barcode', 'pro_bom_code', 'pro_return_allowed',
                'pro_damage_returns_allowed', 'pro_available_for_sale', 'pro_customer_inventory_allowed'
            ]);

        $logDate = now()->format('Y-m-d');
        $logFile = storage_path("logs/product_sync_errors_{$logDate}.log");

        foreach ($clients as $client) {
            try {
                // Apuntar la conexión tenant a este cliente
                Config::set('database.connections.tenant.database', $client->db_name);
                DB::purge('tenant');
                DB::reconnect('tenant');

                // Asegurarse de que las columnas existen en la tabla del tenant
                $this->ensureColumnsExist($client->db_name);

                $updatedCount = 0;
                $insertedCount = 0;
                $skippedCount = 0;

                // Obtener mapeo de SKUs existentes en el tenant para decidir entre Insert o Update
                $tenantProductsMap = DB::connection('tenant')
                    ->table('productos')
                    ->whereNotNull('codigoSKU')
                    ->where('codigoSKU', '<>', '')
                    ->pluck('idproducto', 'codigoSKU');

                // Obtener columnas existentes en este tenant para filtrar el insert/update
                $existingColumns = collect(DB::connection('tenant')->select("SHOW COLUMNS FROM `productos`"))
                    ->pluck('Field')
                    ->toArray();

                foreach ($masterProducts as $master) {
                    $sku = $master->sku;

                    // Datos a sincronizar (mapeo HUB -> Tenant)
                    $syncData = [
                        'marca'     => $master->brand,
                        'class1'    => $master->cl1_code,
                        'class2'    => $master->cl2_code,
                        'class3'    => $master->cl3_code,
                        'class4'    => $master->cl4_code,
                        'unt_code'  => $master->unt_code,
                        'proshortname' => $master->pro_short_name,
                        'probarcode'   => $master->barcode,
                        'bomcode'      => $master->pro_bom_code,
                        'proreturnallowed' => $master->pro_return_allowed,
                        'prodamegereturnsallowed' => $master->pro_damage_returns_allowed,
                        'proavailableforsale' => $master->pro_available_for_sale,
                        'procustomerinventoryallowed' => $master->pro_customer_inventory_allowed,
                    ];

                    // Filtrar solo las columnas que existen en este tenant
                    $filteredSyncData = array_intersect_key($syncData, array_flip($existingColumns));

                    try {
                        if (isset($tenantProductsMap[$sku])) {
                            // UPDATE
                            DB::connection('tenant')
                                ->table('productos')
                                ->where('idproducto', $tenantProductsMap[$sku])
                                ->update($filteredSyncData);
                            $updatedCount++;
                        } else {
                            // INSERT: Combinar con valores por defecto obligatorios existentes
                            $filteredDefaults = array_intersect_key($legacyDefaults, array_flip($existingColumns));
                            
                            $insertData = array_merge($filteredDefaults, $filteredSyncData, [
                                'codigoSKU' => $sku,
                                'producto'  => $master->name,
                            ]);
                            
                            // Asegurar que codigoSKU y producto existen (son obligatorios)
                            $insertData = array_intersect_key($insertData, array_flip($existingColumns));

                            DB::connection('tenant')
                                ->table('productos')
                                ->insert($insertData);
                            $insertedCount++;
                        }
                    } catch (\Exception $e) {
                        $errorMessage = "[{$logDate}] ERROR Tenant: {$client->db_name} | SKU: {$sku} | Motivo: " . $e->getMessage() . PHP_EOL;
                        file_put_contents($logFile, $errorMessage, FILE_APPEND);
                        $skippedCount++;
                    }
                }

                $results['clients_processed']++;
                $results['total_updated'] += $updatedCount;
                $results['total_inserted'] = ($results['total_inserted'] ?? 0) + $insertedCount;
                $results['total_skipped'] += $skippedCount;

                DB::disconnect('tenant');

            } catch (\Exception $e) {
                $results['errors'][] = [
                    'client' => $client->name,
                    'error'  => $e->getMessage(),
                ];
                Log::error("Error crítico en SyncMasterToClients para cliente {$client->name}: " . $e->getMessage());
            }
        }

        return $results;
    }

    private function ensureColumnsExist(string $dbName): void
    {
        $columns = [
            'marca'     => 'VARCHAR(255) NULL',
            'class1'    => 'VARCHAR(50) NULL',
            'class2'    => 'VARCHAR(100) NULL',
            'class3'    => 'VARCHAR(100) NULL',
            'class4'    => 'VARCHAR(100) NULL',
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
