<?php

namespace Modules\ProductsPrice\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\ProductsPrice\Models\MasterProductsPrice;

class MasterProductsPriceBulkSyncAction
{
    /**
     * DICCIONARIO DE MAPEO (Mapper)
     * Si en el futuro necesitas sincronizar otros campos desde la tabla maestra
     * hacia la tabla 'portafolio' en los tenants, solo agrégalos aquí.
     * Formato: ['campo_en_master_products_price' => 'campo_en_portafolio']
     */
    private array $fieldMapper = [
        'precio_compra_caja_con_iva' => 'preciocompra',
        // 'precio_venta_caja_con_iva'  => 'precioventa', // Ejemplo para el futuro
        // 'iva'                        => 'piva',        // Ejemplo para el futuro
    ];

    public function execute(array $data)
    {
        Log::info("Iniciando sincronización masiva de Precios hacia Tenants");

        // 1. Mapear data del Admin a la estructura de la Maestra Central
        $syncData = array_map(function($item) {
            return [
                'lgnstreet1'                 => $item['lgnstreet1'] ?? null,
                'material'                   => $item['material'] ?? null,
                'descripcion'                => $item['descripcion'] ?? null,
                'precio_compra_caja_con_iva' => $item['precio_compra_caja_con_iva'] ?? null,
                'created_at'                 => now(),
                'updated_at'                 => now(),
            ];
        }, $data);

        // 2. Upsert masivo en la tabla maestra central (PolarAPI)
        $this->upsertMasterData($syncData);

        // 3. Obtener todas las rutas (Tenants) disponibles
        $tenants = DB::table('company_routes')
            ->whereNotNull('db_name')
            ->pluck('db_name')
            ->unique();

        Log::info("Precios a sincronizar: " . count($syncData) . ". Tenants encontrados: " . $tenants->count());

        $results = [];

        // 4. Distribuir a cada tenant
        foreach ($tenants as $dbName) {
            try {
                $this->syncToTenant($dbName, $data);
                $results[$dbName] = 'Success';
            } catch (\Exception $e) {
                Log::error("Error sincronizando precios al tenant {$dbName}: " . $e->getMessage());
                $results[$dbName] = 'Error: ' . $e->getMessage();
            }
        }

        return [
            'success' => true,
            'tenants_processed' => count($results),
            'details' => $results
        ];
    }

    protected function upsertMasterData(array $syncData)
    {
        $chunks = array_chunk($syncData, 500);
        foreach ($chunks as $chunk) {
            MasterProductsPrice::upsert($chunk, ['material', 'lgnstreet1'], [
                'descripcion', 'precio_compra_caja_con_iva', 'updated_at'
            ]);
        }
    }

    protected function syncToTenant(string $dbName, array $records)
    {
        // Configurar conexión al tenant
        config(['database.connections.tenant_sync' => array_merge(
            config('database.connections.mysql'),
            ['database' => $dbName]
        )]);
        
        DB::purge('tenant_sync');
        $tenantConnection = DB::connection('tenant_sync');

        // B. Extraer todos los materiales (códigos SKU del Admin)
        $materiales = array_unique(array_column($records, 'material'));

        if (empty($materiales)) {
            return;
        }

        // C. Buscar los productos en el tenant usando codigoSKU = material
        $productosEnTenant = $tenantConnection->table('productos')
            ->whereIn('codigoSKU', $materiales)
            ->pluck('idproducto', 'codigoSKU');

        if ($productosEnTenant->isEmpty()) {
            Log::info("Tenant {$dbName}: No se encontró ningún producto que coincida con los materiales.");
            return;
        }

        // D. Preparar las actualizaciones para la tabla 'portafolio'
        $updatesCount = 0;
        
        $tenantConnection->beginTransaction();
        try {
            foreach ($records as $record) {
                $record = (array)$record;
                $material = $record['material'];

                // Si el producto no existe en este tenant, lo saltamos
                if (!isset($productosEnTenant[$material])) {
                    continue;
                }

                $idProductoTenant = $productosEnTenant[$material];

                // Preparar los campos a actualizar basado en el Diccionario (Mapper)
                $updateData = [];
                foreach ($this->fieldMapper as $masterField => $tenantField) {
                    if (array_key_exists($masterField, $record) && $record[$masterField] !== null) {
                        $updateData[$tenantField] = $record[$masterField];
                    }
                }

                if (!empty($updateData)) {
                    // Actualizamos todas las filas de portafolio que tengan este idproducto (sin importar la ruta)
                    $tenantConnection->table('portafolio')
                        ->where('idproducto', $idProductoTenant)
                        ->update($updateData);
                    
                    $updatesCount++;
                }
            }
            $tenantConnection->commit();
            Log::info("Tenant {$dbName}: Se actualizaron precios para {$updatesCount} productos en el portafolio.");
        } catch (\Exception $e) {
            $tenantConnection->rollBack();
            throw $e;
        }
    }
}
