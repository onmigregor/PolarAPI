<?php

namespace Modules\Analytics\Actions;

use Modules\CompanyRoute\Models\CompanyRoute;
use Modules\Analytics\Models\MasterProduct;
use Modules\Analytics\Models\External\ExtProduct;
use Modules\Analytics\DataTransferObjects\MasterProductData;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class SyncMasterProductsAction
{
    public function execute(): array
    {
        $clients = CompanyRoute::where('is_active', true)->get();
        $syncedCount = 0;
        $errors = [];

        foreach ($clients as $client) {
            try {
                // Configure tenant connection (same credentials, different database)
                Config::set('database.connections.tenant', [
                    'driver' => 'mysql',
                    'host' => env('DB_HOST'),
                    'database' => $client->db_name,
                    'username' => env('DB_USERNAME'),
                    'password' => env('DB_PASSWORD'),
                    'charset' => 'utf8mb4',
                    'collation' => 'utf8mb4_unicode_ci',
                    'prefix' => '',
                ]);

                DB::purge('tenant');

                // Fetch products from external database
                $products = ExtProduct::on('tenant')
                    ->where('producto_activo', 1)
                    ->get();

                foreach ($products as $product) {
                    if (empty($product->codigoSKU)) {
                        continue;
                    }

                    $productData = MasterProductData::fromArray([
                        'sku' => $product->codigoSKU,
                        'name' => $product->producto,
                        'category' => $product->categoria,
                        'brand' => $product->marca,
                        'image' => $product->imagen,
                        'is_active' => (bool) $product->producto_activo,
                    ]);

                    MasterProduct::updateOrCreate(
                        ['sku' => $productData->sku],
                        $productData->toArray()
                    );

                    $syncedCount++;
                }
            } catch (\Exception $e) {
                $errors[] = [
                    'client' => $client->name,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return [
            'synced_count' => $syncedCount,
            'clients_processed' => $clients->count(),
            'errors' => $errors,
        ];
    }
}
