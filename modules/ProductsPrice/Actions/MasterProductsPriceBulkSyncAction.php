<?php

namespace Modules\ProductsPrice\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Modules\CompanyRoute\Models\CompanyRoute;
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
        'precio_venta_caja_con_iva'  => 'precioventa',
        'iva'                        => 'iva',
    ];

    public function execute(array $data)
    {
        Log::info("Iniciando sincronización masiva de Precios hacia Tenants");

        // Obtener códigos SKU únicos (materiales) del payload
        $materiales = array_unique(array_filter(array_map(fn($item) => isset($item['material']) ? trim($item['material']) : null, $data)));

        // Cargar las categorías (cl2_code) desde master_products del Hub
        $masterCategories = [];
        if (!empty($materiales)) {
            $masterCategories = DB::table('master_products')
                ->whereIn('sku', $materiales)
                ->pluck('cl2_code', 'sku')
                ->toArray();
        }

        // 1. Mapear data del Admin a la estructura de la Maestra Central
        $syncData = array_map(function($item) use ($masterCategories) {
            $iva = $item['iva'] ?? null;
            if (is_string($iva)) {
                $iva = trim(str_replace(['%', ','], ['', '.'], $iva));
            }
            $ivaFloat = ($iva !== null && $iva !== '') ? (float)$iva : 0;

            $compraConIva = $item['precio_compra_caja_con_iva'] ?? null;
            $ventaConIva  = $item['precio_venta_caja_con_iva'] ?? null;

            if ($ivaFloat > 0) {
                if (empty($compraConIva) && isset($item['precio_compra_caja_sin_iva'])) {
                    $compraSinIva = (float)$item['precio_compra_caja_sin_iva'];
                    $compraConIva = round($compraSinIva * (1 + ($ivaFloat / 100)), 2);
                }
                if (empty($ventaConIva) && isset($item['precio_venta_caja_sin_iva'])) {
                    $ventaSinIva = (float)$item['precio_venta_caja_sin_iva'];
                    $ventaConIva = round($ventaSinIva * (1 + ($ivaFloat / 100)), 2);
                }
            }

            $material = isset($item['material']) ? trim($item['material']) : null;
            $categoriaMaster = $masterCategories[$material] ?? null;

            return [
                'lgnstreet1'                 => isset($item['lgnstreet1']) ? trim($item['lgnstreet1']) : null,
                'material'                   => $material,
                'descripcion'                => $item['descripcion'] ?? null,
                'precio_compra_caja_con_iva' => $compraConIva,
                'precio_venta_caja_con_iva'  => $ventaConIva,
                'iva'                        => $ivaFloat > 0 ? $ivaFloat : null,
                'categoria'                  => $categoriaMaster,
                'ud_por_cj'                  => $item['ud_por_cj'] ?? null,
                'created_at'                 => now(),
                'updated_at'                 => now(),
            ];
        }, $data);

        // 2. Filtrar y limpiar los campos que van al Upsert en la Maestra central para evitar fallos de base de datos
        $dbMasterData = array_map(function($record) {
            return [
                'lgnstreet1'                 => $record['lgnstreet1'],
                'material'                   => $record['material'],
                'descripcion'                => $record['descripcion'],
                'precio_compra_caja_con_iva' => $record['precio_compra_caja_con_iva'],
                'precio_venta_caja_con_iva'  => $record['precio_venta_caja_con_iva'],
                'iva'                        => $record['iva'],
                'created_at'                 => $record['created_at'],
                'updated_at'                 => $record['updated_at'],
            ];
        }, $syncData);

        $this->upsertMasterData($dbMasterData);

        // 3. Obtener todas las rutas (Tenants) disponibles activos con sus metadatos
        $prefix = config('tenants.prefix', 'www_');
        $suffix = config('tenants.suffix', 'p');
        
        $tenants = CompanyRoute::where('is_active', true)
            ->whereNotNull('db_name')
            ->where('db_name', 'LIKE', "{$prefix}v%{$suffix}")
            ->get();

        Log::info("Precios a sincronizar: " . count($syncData) . ". Tenants encontrados: " . $tenants->count());

        $results = [];

        // 4. Distribuir a cada tenant filtrando por su sale_zone (lgnstreet1)
        foreach ($tenants as $tenant) {
            $dbName = $tenant->db_name;
            $saleZone = $tenant->sale_zone ? trim($tenant->sale_zone) : null;

            // Filtrar precios que correspondan al sale_zone de esta ruta
            $tenantRecords = array_filter($syncData, function($record) use ($saleZone) {
                if (empty($record['lgnstreet1']) || empty($saleZone)) {
                    return false;
                }
                return strcasecmp($record['lgnstreet1'], $saleZone) === 0;
            });

            if (empty($tenantRecords)) {
                Log::info("Tenant {$dbName} (Zone: " . ($saleZone ?? 'N/A') . "): Sin precios correspondientes para sincronizar.");
                continue;
            }

            try {
                $this->syncToTenant($dbName, $tenantRecords);
                $results[$dbName] = 'Success (' . count($tenantRecords) . ' precios)';
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
                'descripcion', 'precio_compra_caja_con_iva', 'precio_venta_caja_con_iva', 'iva', 'updated_at'
            ]);
        }
    }

    protected function syncToTenant(string $dbName, array $records)
    {
        // Configurar conexión al tenant
        Config::set('database.connections.tenant.database', $dbName);
        
        DB::purge('tenant');
        $tenantConnection = DB::connection('tenant');

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

                // Inyectar excento_iva basado en el valor de IVA
                if (isset($updateData['iva'])) {
                    $updateData['excento_iva'] = ((float)$updateData['iva'] > 0) ? 0 : 1;
                }

                // Aplicar división condicional del precio de venta para categorías específicas
                $categoria = isset($record['categoria']) ? strtoupper(trim($record['categoria'])) : '';
                $specialCategories = ['NAACFH', 'NAACMA'];
                
                if (in_array($categoria, $specialCategories)) {
                    $udPorCj = isset($record['ud_por_cj']) ? (int)$record['ud_por_cj'] : 0;
                    if ($udPorCj > 0 && isset($updateData['precioventa'])) {
                        $updateData['precioventa'] = round((float)$updateData['precioventa'] / $udPorCj, 4);
                    }
                }

                if (!empty($updateData)) {
                    // Actualizamos la tabla productos del tenant usando el codigoSKU (material)
                    $tenantConnection->table('productos')
                        ->where('codigoSKU', $material)
                        ->update($updateData);
                    
                    $updatesCount++;
                }
            }
            $tenantConnection->commit();
            Log::info("Tenant {$dbName}: Se actualizaron precios e IVA para {$updatesCount} productos en la tabla productos.");
        } catch (\Exception $e) {
            $tenantConnection->rollBack();
            throw $e;
        }
    }
}
