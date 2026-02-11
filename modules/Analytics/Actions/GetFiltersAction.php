<?php

namespace Modules\Analytics\Actions;

use Modules\Client\Models\Client;
use Modules\Region\Models\Region;
use Modules\Analytics\Models\MasterProduct;

class GetFiltersAction
{
    public function execute(): array
    {
        return [
            'clients' => Client::select('id', 'name', 'region_id')
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
                ->map(fn($client) => [
                    'id' => $client->id,
                    'name' => $client->name,
                    'region_id' => $client->region_id,
                ]),

            'regions' => Region::select('id', 'name')
                ->orderBy('name')
                ->get()
                ->map(fn($region) => [
                    'id' => $region->id,
                    'name' => $region->name,
                ]),

            'products' => MasterProduct::select('id', 'sku', 'name', 'category', 'brand')
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
                ->map(fn($product) => [
                    'id' => $product->id,
                    'sku' => $product->sku,
                    'name' => $product->name,
                    'category' => $product->category,
                    'brand' => $product->brand,
                ]),
        ];
    }
}
