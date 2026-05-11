<?php

namespace Modules\MasterPromotion\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\MasterProduct\Models\MasterProduct;

/**
 * Acción para asegurar que los productos referenciados en promociones existan en el Tenant.
 * Si un producto no existe, lo crea usando la información maestra del HUB.
 */
class EnsurePromotionProductsExistAction
{
    /**
     * @param string $tenantDb Nombre de la base de datos del tenant
     * @param array $productCodes Lista de SKUs (pro_code) a verificar
     * @return array Resumen de productos creados
     */
    public function execute(string $tenantDb, array $productCodes): array
    {
        if (empty($productCodes)) {
            return ['created' => 0, 'existing' => 0];
        }

        // 0. Verificar si la tabla existe en este tenant
        if (!\Illuminate\Support\Facades\Schema::connection('tenant')->hasTable('productos')) {
            Log::warning("EnsurePromotionProductsExist: La tabla 'productos' no existe en el tenant {$tenantDb}. Saltando creación.");
            return ['created' => 0, 'existing' => 0, 'error' => 'table_not_found'];
        }

        // 1. Identificar cuáles productos ya existen en el Tenant
        $existingCodes = DB::connection('tenant')->table('productos')
            ->whereIn('codigoSKU', $productCodes)
            ->pluck('codigoSKU')
            ->toArray();

        $missingCodes = array_diff($productCodes, $existingCodes);

        if (empty($missingCodes)) {
            return ['created' => 0, 'existing' => count($existingCodes)];
        }

        // 2. Obtener data maestra del HUB para los productos faltantes
        $masterProducts = MasterProduct::whereIn('sku', $missingCodes)->get();

        if ($masterProducts->isEmpty()) {
            Log::warning("EnsurePromotionProductsExist: Se requieren productos (" . implode(',', $missingCodes) . ") pero no existen en MasterProduct del HUB.");
            return ['created' => 0, 'existing' => count($existingCodes), 'missing_in_hub' => count($missingCodes)];
        }

        $createdCount = 0;
        $now = now();

        foreach ($masterProducts as $product) {
            try {
                DB::connection('tenant')->table('productos')->insert([
                    'codigoSKU'                 => $product->sku,
                    'producto'                  => $product->name,
                    'marca'                     => $product->brand ?? '',
                    'categoria'                 => $product->category ?? '',
                    'tipo'                      => 'PRODUCTO', // Valor estándar
                    'grupo_precio'              => 'GENERAL',  // Valor estándar
                    'unt_code'                  => $product->unt_code ?? '',
                    'class1'                    => $product->cl1_code ?? '',
                    'class2'                    => $product->cl2_code ?? '',
                    'class3'                    => $product->cl3_code ?? '',
                    'class4'                    => $product->cl4_code ?? '',
                    'proshortname'              => $product->pro_short_name ?? '',
                    'probarcode'                => $product->barcode ?? '',
                    'bomcode'                   => $product->pro_bom_code ?? '',
                    'proreturnallowed'          => $product->pro_return_allowed ? 1 : 0,
                    'prodamegereturnsallowed'   => $product->pro_damage_returns_allowed ? 1 : 0,
                    'proavailableforsale'       => $product->pro_available_for_sale ? 1 : 0,
                    'procustomerinventoryallowed' => $product->pro_customer_inventory_allowed ? 1 : 0,
                    'producto_activo'           => 1,
                    'stock'                     => 0,
                    'precioventa'               => 0,
                    'preciocompra'              => 0,
                ]);
                $createdCount++;
            } catch (\Exception $e) {
                Log::error("EnsurePromotionProductsExist: Error al crear producto {$product->sku} en {$tenantDb}: " . $e->getMessage());
            }
        }

        return [
            'created'  => $createdCount,
            'existing' => count($existingCodes),
        ];
    }
}
