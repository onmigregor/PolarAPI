<?php

namespace Modules\MasterProduct\Actions;

use Modules\CompanyRoute\Models\CompanyRoute;
use Modules\MasterProduct\Models\MasterProduct;
use Modules\MasterProduct\Models\External\ExtClientProduct;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncClientProductsAction
{
    public function execute(): array
    {
        $clients = CompanyRoute::where('is_active', true)->get();
        $syncedCountCreated = 0;
        $syncedCountUpdated = 0;
        $errors = [];

        foreach ($clients as $client) {
            try {
                // Configure tenant connection (dynamic per client)
                Config::set('database.connections.tenant.database', $client->db_name);
                DB::purge('tenant');
                DB::reconnect('tenant');

                // Fetch active products from tenant database
                $products = ExtClientProduct::on('tenant')
                    ->where('producto_activo', 1)
                    ->get();

                foreach ($products as $product) {
                    if (empty($product->codigoSKU)) {
                        continue;
                    }

                    // Buscar si existe el producto
                    $masterProduct = MasterProduct::firstOrNew(['sku' => $product->codigoSKU]);
                    
                    if (!$masterProduct->exists) {
                        $syncedCountCreated++;
                    } else {
                        $syncedCountUpdated++;
                    }

                    // Actualizamos solo campos básicos del cliente, no tocamos códigos jerárquicos de clases ni unidades
                    $masterProduct->name      = $product->producto;
                    $masterProduct->category  = $product->categoria;
                    $masterProduct->image     = $product->imagen;
                    $masterProduct->is_active = (bool)$product->producto_activo;
                    $masterProduct->save();
                }

                DB::disconnect('tenant');

            } catch (\Exception $e) {
                $errors[] = [
                    'client' => $client->name,
                    'error' => $e->getMessage(),
                ];
                Log::error("Error en SyncClientProducts para cliente {$client->name}: " . $e->getMessage());
            }
        }

        return [
            'created_count' => $syncedCountCreated,
            'updated_count' => $syncedCountUpdated,
            'clients_processed' => $clients->count(),
            'errors' => $errors,
        ];
    }
}
