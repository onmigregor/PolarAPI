<?php

namespace Modules\MasterProduct\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\MasterProduct\Models\MasterProduct;

/**
 * Acción para sincronizar el catálogo completo de productos maestros a los Tenants.
 */
class SyncMasterProductsToTenantsAction
{
    public function execute(string $tenantDb): array
    {
        $summary = [
            'total_master' => 0,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
        ];

        try {
            Log::info("SyncMasterProductsToTenants: Iniciando sincronización total para '{$tenantDb}'");

            // 1. Obtener la información de la ruta en el HUB para el campo 'ruta'
            $companyRoute = \Modules\CompanyRoute\Models\CompanyRoute::where('db_name', $tenantDb)->firstOrFail();
            $routeName = $companyRoute->route_name;

            // 2. Obtener todos los productos maestros activos del HUB
            $masterProducts = MasterProduct::where('is_active', true)->get();
            $summary['total_master'] = $masterProducts->count();

            if ($masterProducts->isEmpty()) {
                return $summary;
            }

            // 2. Obtener las columnas reales de la tabla para no enviar campos inexistentes
            config(['database.connections.tenant.database' => $tenantDb]);
            DB::purge('tenant');

            $tableColumns = \Illuminate\Support\Facades\Schema::connection('tenant')->getColumnListing('productos');
            $existingProducts = DB::connection('tenant')->table('productos')
                ->select('idproducto', 'codigoSKU')
                ->get()
                ->keyBy('codigoSKU');

            $toInsert = [];
            $now = date('Y-m-d');

            $legacyDefaults = [
                'ruta' => $routeName,
                'descripcion1' => '',
                'descripcion2' => '',
                'imagen' => 'no-image.png',
                'grupo' => 6,
                'er' => 0,
                'categoria' => 'POMAR',
                'tipo' => 'PRODUCTO',
                'unidadesporcaja' => 1,
                'stock' => 0,
                'stock_min' => 0,
                'presentacion' => '',
                'litros' => '',
                'gl' => '',
                'tKGML' => 0,
                'KGML' => 0,
                'precioventa' => 0,
                'precioventabs' => 0,
                'porcentajesugerido' => 0,
                'sugeridoventa' => 0,
                'preciocompra' => 0,
                'montoganancia' => 0,
                'porcentajeganancia' => 0,
                'fechaprecio' => $now,
                'baseimponible' => 0,
                'iva' => 0,
                'excento_iva' => 0,
                'grupo_precio' => 'GENERAL',
                'producto_destacado' => 0,
                'producto_destacado2' => 0,
                'producto_en_promocion' => 0,
                'producto_activo' => 1,
                'textvoice' => '',
                'graficar' => 0,
            ];

            foreach ($masterProducts as $product) {
                $sku = $product->sku;

                // Solo los campos dinámicos provenientes del catálogo maestro que deben actualizarse
                $syncData = [
                    'producto' => $product->name,
                    'marca' => $product->brand ?? 'POMAR',
                    'codigobarras' => $product->barcode ?? '',
                    'familia' => $product->cl1_code ?? '',
                    'segmento' => $product->cl4_code ?? '',
                    'unt_code' => $product->unt_code ?? '',
                    'class1' => $product->cl1_code ?? '',
                    'class2' => $product->cl2_code ?? '',
                    'class3' => $product->cl3_code ?? '',
                    'class4' => $product->cl4_code ?? '',
                    'proshortname' => $product->pro_short_name ?? '',
                    'probarcode' => $product->barcode ?? '',
                    'bomcode' => $product->pro_bom_code ?? '',
                    'proreturnallowed' => $product->pro_return_allowed ? 1 : 0,
                    'prodamegereturnsallowed' => $product->pro_damage_returns_allowed ? 1 : 0,
                    'proavailableforsale' => $product->pro_available_for_sale ? 1 : 0,
                    'procustomerinventoryallowed' => $product->pro_customer_inventory_allowed ? 1 : 0,
                ];

                if ($existingProducts->has($sku)) {
                    // UPDATE: Filtrar solo las columnas reales existentes y que están en syncData
                    $updateData = array_intersect_key($syncData, array_flip($tableColumns));

                    DB::connection('tenant')->table('productos')
                        ->where('codigoSKU', $sku)
                        ->update($updateData);
                    $summary['updated']++;
                } else {
                    // INSERT: Combinar legacy defaults, datos maestros y el código SKU
                    $insertData = array_merge($legacyDefaults, $syncData, ['codigoSKU' => $sku]);
                    $filteredInsertData = array_intersect_key($insertData, array_flip($tableColumns));

                    $toInsert[] = $filteredInsertData;
                }
            }

            // 3. Inserción masiva de los nuevos productos (en bloques de 100)
            if (!empty($toInsert)) {
                foreach (array_chunk($toInsert, 100) as $chunk) {
                    DB::connection('tenant')->table('productos')->insert($chunk);
                }
                $summary['created'] = count($toInsert);
            }

            Log::info("SyncMasterProductsToTenants: Finalizado para '{$tenantDb}'. Maestro: {$summary['total_master']}, Creados: {$summary['created']}, Actualizados: {$summary['updated']}");

            return $summary;

        } catch (\Exception $e) {
            Log::error("SyncMasterProductsToTenants: Error en {$tenantDb}: " . $e->getMessage());
            throw $e;
        }
    }
}
