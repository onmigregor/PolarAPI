<?php

namespace Modules\MasterDiscount\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\MasterDiscount\Models\MasterDiscount;
use Modules\MasterDiscount\Models\MasterDiscountDetail;
use Modules\MasterDiscount\Models\MasterDiscountDetailProduct;
use Modules\MasterDiscount\Models\MasterDiscountRoute;

class MasterDiscountAction
{
    public function execute(array $payload): array
    {
        Log::channel('single')->info("=== INICIO DE ESPEJO DE DESCUENTOS EN HUB ===");

        $results = [
            'discounts' => 0,
            'details' => 0,
            'products' => 0,
            'routes' => 0,
            'errors' => [],
        ];

        $discountsData = $payload['discounts'] ?? $payload['discount'] ?? [];
        $detailsData    = $payload['details']    ?? $payload['discountDetail'] ?? [];
        $productsData   = $payload['products']   ?? $payload['discountDetailProduct'] ?? [];
        $routesData     = $payload['routes']     ?? $payload['discountDetailRoute'] ?? [];

        try {
            DB::beginTransaction();

            // 1. Sync Discounts
            if (!empty($discountsData)) {
                $fillable = (new MasterDiscount())->getFillable();
                $data = $this->filterAndMap($discountsData, $fillable);
                MasterDiscount::upsert($data, ['dis_code'], array_diff($fillable, ['dis_code']));
                $results['discounts'] = count($data);
            }

            // 2. Sync Details
            if (!empty($detailsData)) {
                $fillable = (new MasterDiscountDetail())->getFillable();
                $data = $this->filterAndMap($detailsData, $fillable);
                MasterDiscountDetail::upsert($data, ['did_code'], array_diff($fillable, ['did_code', 'dis_code']));
                $results['details'] = count($data);
            }

            // 3. Sync Products
            if (!empty($productsData)) {
                $fillable = (new MasterDiscountDetailProduct())->getFillable();
                $data = $this->filterAndMap($productsData, $fillable);
                if (!empty($data)) {
                    MasterDiscountDetailProduct::upsert($data, ['dlp_code'], array_diff($fillable, ['dlp_code', 'did_code', 'dis_code']));
                    $results['products'] = count($data);
                }
            }

            // 4. Sync Routes (Delete and Re-insert like Promotions)
            if (!empty($routesData)) {
                $mappedRoutes = $this->filterAndMap($routesData, (new MasterDiscountRoute())->getFillable());
                if (!empty($mappedRoutes)) {
                    $disCodes = collect($mappedRoutes)->pluck('dis_code')->unique()->toArray();
                    MasterDiscountRoute::whereIn('dis_code', $disCodes)->delete();
                    MasterDiscountRoute::insert($mappedRoutes);
                    $results['routes'] = count($mappedRoutes);
                }
            }

            DB::commit();
            Log::channel('single')->info("=== ESPEJO DE DESCUENTOS COMPLETADO EXITOSAMENTE ===");

        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('single')->error("Error en Espejo de Descuentos: " . $e->getMessage() . " en " . $e->getFile() . ":" . $e->getLine());
            $results['errors'][] = $e->getMessage();
        }

        return $results;
    }

    private function filterAndMap(array $items, array $fillable): array
    {
        $now = now();
        $mappedItems = [];

        foreach ($items as $item) {
            $newItem = [];
            foreach ($item as $key => $value) {
                // Convertir camelCase a snake_case
                $snakeKey = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $key));
                
                if (in_array($snakeKey, $fillable)) {
                    if (is_string($value)) {
                        $value = trim($value);
                        if ($value === "") $value = null;
                    }
                    
                    if (is_bool($value)) {
                        $value = $value ? 1 : 0;
                    }

                    if ($snakeKey === 'cus_code' && is_string($value)) {
                        $value = ltrim($value, '0');
                    }

                    $newItem[$snakeKey] = $value;
                }
            }
            
            if (!empty($newItem)) {
                $newItem['created_at'] = $now;
                $newItem['updated_at'] = $now;
                $mappedItems[] = $newItem;
            }
        }

        return $mappedItems;
    }
}
