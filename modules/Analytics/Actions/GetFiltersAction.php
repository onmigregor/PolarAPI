<?php

namespace Modules\Analytics\Actions;

use Modules\CompanyRoute\Models\CompanyRoute;
use Modules\Region\Models\Region;
use Modules\MasterProduct\Models\MasterProduct;
use Modules\MasterProduct\Models\MasterProductFamily;
use Modules\MasterProduct\Models\MasterProductCategory;
use Modules\MasterProduct\Models\MasterProductClass3;
use Modules\MasterProduct\Models\MasterProductClass4;

class GetFiltersAction
{
    public function execute(): array
    {
        return [
            'routes' => CompanyRoute::select('id', 'name', 'db_name', 'region_id')
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
                ->map(fn($route) => [
                    'id' => $route->id,
                    'name' => $route->name,
                    'db_name' => $route->db_name,
                    'region_id' => $route->region_id,
                ]),

            'fq_codes' => CompanyRoute::select('cep')
                ->where('is_active', true)
                ->whereNotNull('cep')->where('cep', '!=', '')
                ->distinct()
                ->pluck('cep')
                ->map(fn($v) => [
                    'id' => str_pad($v, 10, '0', STR_PAD_LEFT),
                    'name' => str_pad($v, 10, '0', STR_PAD_LEFT)
                ]),

            'vendor_groups' => CompanyRoute::select('address_street2')
                ->where('is_active', true)
                ->whereNotNull('address_street2')->where('address_street2', '!=', '')
                ->distinct()
                ->pluck('address_street2')
                ->map(fn($v) => ['id' => $v, 'name' => $v]),

            'offices' => CompanyRoute::select('address_street1')
                ->where('is_active', true)
                ->whereNotNull('address_street1')->where('address_street1', '!=', '')
                ->distinct()
                ->pluck('address_street1')
                ->map(fn($v) => ['id' => $v, 'name' => $v]),

            'territories' => CompanyRoute::select('subregion_code')
                ->where('is_active', true)
                ->whereNotNull('subregion_code')->where('subregion_code', '!=', '')
                ->distinct()
                ->pluck('subregion_code')
                ->map(fn($v) => ['id' => $v, 'name' => $v]),

            'regions' => Region::select('id', 'citCode', 'citName')
                ->orderBy('citName')
                ->get()
                ->map(fn($region) => [
                    'id' => $region->id,
                    'code' => $region->citCode,
                    'name' => $region->citName,
                ]),

            'families' => MasterProductFamily::select('cl1_code', 'cl1_name')
                ->orderBy('cl1_name')
                ->get()
                ->map(fn($f) => [
                    'id' => $f->cl1_code,
                    'name' => $f->cl1_name,
                ]),

            'categories' => MasterProductCategory::select('cl2_code', 'cl2_name', 'cl1_code')
                ->orderBy('cl2_name')
                ->get()
                ->map(fn($c) => [
                    'id' => $c->cl2_code,
                    'name' => $c->cl2_name,
                    'cl1_code' => $c->cl1_code,
                ]),

            'brands' => MasterProductClass4::select('brand_code', 'cl4_name')
                ->whereNotNull('brand_code')
                ->where('brand_code', '!=', '')
                ->distinct()
                ->orderBy('cl4_name')
                ->get()
                ->map(fn($b) => [
                    'id' => $b->brand_code,
                    'name' => $b->cl4_name,
                ]),

            'segments' => MasterProductClass3::select('cl3_code', 'cl3_name')
                ->whereNotNull('cl3_code')
                ->where('cl3_code', '!=', '')
                ->distinct()
                ->orderBy('cl3_name')
                ->get()
                ->map(fn($s) => [
                    'id' => $s->cl3_code,
                    'name' => $s->cl3_name,
                ]),

            'products' => MasterProduct::select('id', 'sku', 'name', 'category', 'brand', 'cl1_code', 'cl2_code', 'cl3_code', 'brand_code', 'segment_code')
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
                ->map(fn($product) => [
                    'id' => $product->id,
                    'sku' => $product->sku,
                    'name' => $product->name,
                    'category' => $product->category,
                    'brand' => $product->brand,
                    'cl1_code' => $product->cl1_code,
                    'cl2_code' => $product->cl2_code,
                    'cl3_code' => $product->cl3_code,
                    'brand_code' => $product->brand_code,
                    'segment_code' => $product->segment_code,
                ]),
        ];
    }
}
