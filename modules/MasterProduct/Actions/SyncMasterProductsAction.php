<?php

namespace Modules\MasterProduct\Actions;

use Modules\MasterProduct\Models\MasterProduct;
use Modules\MasterProduct\Models\MasterProductFamily;
use Modules\MasterProduct\Models\MasterProductCategory;
use Modules\MasterProduct\Models\MasterProductClass3;
use Modules\MasterProduct\Models\MasterUnit;
use Modules\MasterProduct\Models\MasterProductUnit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncMasterProductsAction
{
    public function execute(): array
    {
        $results = [
            'units'         => 0,
            'families'      => 0,
            'categories'    => 0,
            'class3'        => 0,
            'products'      => 0,
            'product_units' => 0,
            'errors'        => [],
        ];

        try {
            // Use the dedicated connection 'productos_polar' defined in config/database.php
            $db = DB::connection('productos_polar');

            // 1. Sync Units
            $units = $db->table('units')->get();
            foreach ($units as $unit) {
                if (empty($unit->unt_code)) continue;
                MasterUnit::updateOrCreate(
                    ['unt_code' => $unit->unt_code],
                    [
                        'unt_name' => $unit->unt_name,
                        'unt_nick' => $unit->unt_nick ?? null,
                    ]
                );
                $results['units']++;
            }

            // 2. Sync Product Families (cl1)
            $families = $db->table('product_families')->get();
            foreach ($families as $family) {
                if (empty($family->cl1_code)) continue;
                MasterProductFamily::updateOrCreate(
                    ['cl1_code' => $family->cl1_code],
                    ['cl1_name' => $family->cl1_name]
                );
                $results['families']++;
            }

            // 3. Sync Product Categories (cl2)
            $categories = $db->table('product_categories')->get();
            foreach ($categories as $category) {
                if (empty($category->cl2_code)) continue;
                MasterProductCategory::updateOrCreate(
                    ['cl2_code' => $category->cl2_code],
                    [
                        'cl1_code' => $category->cl1_code,
                        'cl2_name' => $category->cl2_name,
                    ]
                );
                $results['categories']++;
            }

            // 4. Sync Product Class 3 (cl3)
            $class3s = $db->table('product_class_3')->get();
            foreach ($class3s as $c3) {
                if (empty($c3->cl3_code)) continue;
                MasterProductClass3::updateOrCreate(
                    ['cl3_code' => $c3->cl3_code],
                    [
                        'cl2_code' => $c3->cl2_code,
                        'cl3_name' => $c3->cl3_name,
                    ]
                );
                $results['class3']++;
            }

            // 5. Sync Products (Enrich existing products only)
            $products = $db->table('products')->get();
            foreach ($products as $product) {
                if (empty($product->pro_code)) continue;
                
                // Only update existing products created by the clients sync
                $updated = MasterProduct::where('sku', $product->pro_code)
                    ->update([
                        'brand'     => $product->pro_organization,
                        'cl2_code'  => $product->cl2_code,
                        'cl3_code'  => $product->cl3_code,
                        'cl4_code'  => $product->cl4_code,
                        'brand_code' => $product->brand_code,
                        'segment_code' => $product->segment_code,
                        'barcode'   => $product->pro_barcode,
                        'pro_short_name' => $product->pro_short_name,
                        'pro_bom_code' => $product->pro_bom_code,
                        'pro_return_allowed' => $product->pro_return_allowed,
                        'pro_damage_returns_allowed' => $product->pro_damage_returns_allowed,
                        'pro_available_for_sale' => $product->pro_available_for_sale,
                        'pro_customer_inventory_allowed' => $product->pro_customer_inventory_allowed,
                        'unt_code'  => $product->unt_code,
                    ]);
                    
                if ($updated > 0) {
                    $results['products']++;
                }
            }

            // 6. Sync Product Units
            $productUnits = $db->table('product_units')->get();
            foreach ($productUnits as $pu) {
                if (empty($pu->pro_code) || empty($pu->unt_code)) continue;
                MasterProductUnit::updateOrCreate(
                    ['pro_code' => $pu->pro_code, 'unt_code' => $pu->unt_code],
                    [
                        'pru_multiply_by' => $pu->pru_multiply_by ?? null,
                        'pru_divide_by' => $pu->pru_divide_by ?? null,
                        'pru_bar_code'  => $pu->pru_bar_code ?? null,
                    ]
                );
                $results['product_units']++;
            }

        } catch (\Exception $e) {
            $results['errors'][] = [
                'source' => config('database.connections.productos_polar.database'),
                'error'  => $e->getMessage(),
            ];
            Log::error('SyncMasterProducts error: ' . $e->getMessage());
        }

        return $results;
    }
}
