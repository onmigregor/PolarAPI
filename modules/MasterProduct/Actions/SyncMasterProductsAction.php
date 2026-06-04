<?php

namespace Modules\MasterProduct\Actions;

use Modules\MasterProduct\Models\MasterProduct;
use Modules\MasterProduct\Models\MasterProductFamily;
use Modules\MasterProduct\Models\MasterProductCategory;
use Modules\MasterProduct\Models\MasterProductClass3;
use Modules\MasterProduct\Models\MasterProductClass4;
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
            'class4'        => 0,
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
                $untCode = trim($unit->unt_code);
                MasterUnit::updateOrCreate(
                    ['unt_code' => $untCode],
                    [
                        'unt_name' => $unit->unt_name,
                        'unt_nick' => $unit->unt_nick ?? null,
                    ]
                );
                $results['units']++;
            }

            // 2. Sync Product Families (cl1)
            $families = $db->table('product_class1')->get();
            foreach ($families as $family) {
                $cl1Code = trim($family->cl1_code);
                MasterProductFamily::updateOrCreate(
                    ['cl1_code' => $cl1Code],
                    ['cl1_name' => $family->cl1_name]
                );
                $results['families']++;
            }

            // 3. Sync Product Categories (cl2)
            $categories = $db->table('product_class2')->get();
            foreach ($categories as $category) {
                $cl2Code = trim($category->cl2_code);
                MasterProductCategory::updateOrCreate(
                    ['cl2_code' => $cl2Code],
                    [
                        'cl1_code' => trim($category->cl1_code),
                        'cl2_name' => $category->cl2_name,
                    ]
                );
                $results['categories']++;
            }

            // 4. Sync Product Class 3 (cl3)
            $class3s = $db->table('product_class3')->get();
            foreach ($class3s as $c3) {
                $cl3Code = trim($c3->cl3_code);
                MasterProductClass3::updateOrCreate(
                    ['cl3_code' => $cl3Code],
                    [
                        'cl2_code' => trim($c3->cl2_code),
                        'cl3_name' => $c3->cl3_name,
                    ]
                );
                $results['class3']++;
            }

            // 4.5. Sync Product Class 4 (cl4)
            $class4s = $db->table('product_class4')->get();
            foreach ($class4s as $c4) {
                $cl4Code = trim($c4->cl4_code);
                MasterProductClass4::withTrashed()->updateOrCreate(
                    ['cl4_code' => $cl4Code],
                    [
                        'cl4_name'     => $c4->cl4_name,
                        'brand_code'   => trim($c4->brand_code),
                        'segment_code' => trim($c4->segment_code),
                        'cl3_code'     => trim($c4->cl3_code ?? ''),
                    ]
                );
                $results['class4']++;
            }

            // 5. Sync Products (Create or Update)
            $products = $db->table('products')->get();
            foreach ($products as $product) {
                $sku = trim($product->pro_code);
                MasterProduct::updateOrCreate(
                    ['sku' => $sku],
                    [
                        'name'       => $product->pro_name,
                        'brand'      => $product->pro_organization,
                        'cl1_code'   => trim($product->cl1_code ?? ''),
                        'cl2_code'   => trim($product->cl2_code ?? ''),
                        'cl3_code'   => trim($product->cl3_code ?? ''),
                        'cl4_code'   => trim($product->cl4_code ?? ''),
                        'brand_code' => trim($product->brand_code ?? ''),
                        'segment_code' => trim($product->segment_code ?? ''),
                        'barcode'    => trim($product->pro_barcode ?? ''),
                        'pro_short_name' => $product->pro_short_name,
                        'pro_bom_code' => $product->pro_bom_code,
                        'pro_return_allowed' => $product->pro_return_allowed,
                        'pro_damage_returns_allowed' => $product->pro_damage_returns_allowed,
                        'pro_available_for_sale' => $product->pro_available_for_sale,
                        'pro_customer_inventory_allowed' => $product->pro_customer_inventory_allowed,
                        'unt_code'   => trim($product->unt_code ?? ''),
                    ]
                );
                
                $results['products']++;
            }

            // 6. Sync Product Units
            $productUnits = $db->table('product_units')->get();
            foreach ($productUnits as $pu) {
                $proCode = trim($pu->pro_code);
                $untCode = trim($pu->unt_code);
                MasterProductUnit::withTrashed()->updateOrCreate(
                    ['pro_code' => $proCode, 'unt_code' => $untCode],
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
