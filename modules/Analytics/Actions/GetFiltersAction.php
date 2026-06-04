<?php

namespace Modules\Analytics\Actions;

use Modules\CompanyRoute\Models\CompanyRoute;
use Modules\Region\Models\Region;
use Modules\MasterProduct\Models\MasterProduct;
use Modules\MasterProduct\Models\MasterProductFamily;
use Modules\MasterProduct\Models\MasterProductCategory;

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

            'brands' => MasterProduct::select('brand_code')
                ->whereNotNull('brand_code')
                ->where('brand_code', '!=', '')
                ->distinct()
                ->orderBy('brand_code')
                ->pluck('brand_code')
                ->map(fn($b) => [
                    'id' => $b,
                    'name' => $b,
                ]),

            'segments' => MasterProduct::select('segment_code')
                ->whereNotNull('segment_code')
                ->where('segment_code', '!=', '')
                ->distinct()
                ->orderBy('segment_code')
                ->pluck('segment_code')
                ->map(fn($s) => [
                    'id' => $s,
                    'name' => $s,
                ]),

            'products' => MasterProduct::select('id', 'sku', 'name', 'category', 'brand', 'cl1_code', 'cl2_code', 'brand_code', 'segment_code')
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
                    'brand_code' => $product->brand_code,
                    'segment_code' => $product->segment_code,
                ]),
        ];
    }
}
