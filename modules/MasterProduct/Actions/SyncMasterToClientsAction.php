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
    /**
     * Columnas a agregar/actualizar en los tenants.
     * Key: nombre de columna en la tabla `productos` del tenant.
     * Value: nombre de columna en master_products.
     */
    private const COLUMN_MAPPING = [
        'brand'     => 'brand',
        'cl2_code'  => 'cl2_code',
        'cl3_code'  => 'cl3_code',
        'unt_code'  => 'unt_code',
    ];

    public function execute(): array
    {
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
            ->get(['sku', 'brand', 'cl2_code', 'cl3_code', 'unt_code'])
            ->keyBy('sku');

        foreach ($clients as $client) {
            try {
                // Apuntar la conexión tenant a este cliente
                Config::set('database.connections.tenant.database', $client->db_name);
                DB::purge('tenant');
                DB::reconnect('tenant');

                // Asegurarse de que las columnas existen en la tabla del tenant solo si no se ha sincronizado antes
                if (!$client->is_available_to_sync) {
                    $this->ensureColumnsExist($client->db_name);
                    
                    // Marcar como disponible para futuras ejecuciones
                    $client->is_available_to_sync = true;
                    $client->save();
                }

                $updatedCount = 0;
                $skippedCount = 0;

                // Obtener todos los SKUs activos de este tenant junto con sus valores actuales
                $tenantProducts = DB::connection('tenant')
                    ->table('productos')
                    ->select('idproducto', 'codigoSKU', 'brand', 'cl2_code', 'cl3_code', 'unt_code')
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
                        $tenantProduct->brand === $master->brand &&
                        $tenantProduct->cl2_code === $master->cl2_code &&
                        $tenantProduct->cl3_code === $master->cl3_code &&
                        $tenantProduct->unt_code === $master->unt_code
                    ) {
                        $results['total_unchanged'] = ($results['total_unchanged'] ?? 0) + 1;
                        continue;
                    }

                    DB::connection('tenant')
                        ->table('productos')
                        ->where('idproducto', $tenantProduct->idproducto)
                        ->update([
                            'brand'    => $master->brand,
                            'cl2_code' => $master->cl2_code,
                            'cl3_code' => $master->cl3_code,
                            'unt_code' => $master->unt_code,
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

    /**
     * Agrega dinámicamente las columnas al tenant si no existen.
     */
    private function ensureColumnsExist(string $dbName): void
    {
        $columns = [
            'brand'    => 'VARCHAR(255) NULL',
            'cl2_code' => 'VARCHAR(50) NULL',
            'cl3_code' => 'VARCHAR(60) NULL',
            'unt_code' => 'VARCHAR(20) NULL',
        ];

        foreach ($columns as $column => $definition) {
            $exists = DB::connection('tenant')
                ->select("
                    SELECT COUNT(*) as count
                    FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = ?
                    AND TABLE_NAME = 'productos'
                    AND COLUMN_NAME = ?
                ", [$dbName, $column]);

            if ($exists[0]->count === 0) {
                DB::connection('tenant')
                    ->statement("ALTER TABLE `productos` ADD COLUMN `{$column}` {$definition}");
                Log::info("Columna `{$column}` agregada a `{$dbName}.productos`");
            }
        }
    }
}
