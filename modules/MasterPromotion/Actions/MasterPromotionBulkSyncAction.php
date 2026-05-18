<?php

namespace Modules\MasterPromotion\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\MasterPromotion\Models\MasterPromotion;
use Modules\MasterPromotion\Models\MasterPromotionDetail;
use Modules\MasterPromotion\Models\MasterPromotionDetailProduct;
use Modules\MasterPromotion\Models\MasterPromotionRoute;
use Modules\MasterPromotion\Models\MasterPromotionTeam;

class MasterPromotionBulkSyncAction
{
    public function execute(array $payload): array
    {
        Log::channel('single')->info("=== INICIO DE ESPEJO DE PROMOCIONES EN HUB ===");

        $results = [
            'promotions' => 0,
            'details' => 0,
            'products' => 0,
            'routes' => 0,
            'teams' => 0,
            'errors' => [],
        ];

        // Mapeo de claves del JSON a las claves esperadas por la acción (basadas en el payload del Admin)
        // Si el payload viene directamente de la estructura del JSON "value", ajustamos las claves.
        $promotionsData = $payload['promotions'] ?? $payload['promotion'] ?? [];
        $detailsData    = $payload['details']    ?? $payload['promotionDetail'] ?? [];
        $productsData   = $payload['products']   ?? $payload['promotionDetailProduct'] ?? [];
        $routesData     = $payload['routes']     ?? $payload['promotionRoute'] ?? [];
        $teamsData      = $payload['teams']      ?? $payload['promotionTeam'] ?? [];

        try {
            DB::beginTransaction();

            // 1. Sync Promotions (Headers)
            if (!empty($promotionsData)) {
                $fillable = (new MasterPromotion())->getFillable();
                $data = $this->filterAndMap($promotionsData, $fillable);
                MasterPromotion::upsert($data, ['prm_code'], array_diff($fillable, ['prm_code']));
                $results['promotions'] = count($data);
            }

            // 2. Sync Details
            if (!empty($detailsData)) {
                $fillable = (new MasterPromotionDetail())->getFillable();
                $data = $this->filterAndMap($detailsData, $fillable);
                MasterPromotionDetail::upsert($data, ['pdl_code'], array_diff($fillable, ['pdl_code', 'prm_code']));
                $results['details'] = count($data);
            }

            // 3. Sync Products
            if (!empty($productsData)) {
                // REQUERIMIENTO ESPECIAL: Filtrar solo prpRequired == true
                $filteredProducts = array_filter($productsData, function ($item) {
                    $required = $item['prp_required'] ?? $item['prpRequired'] ?? false;
                    return filter_var($required, FILTER_VALIDATE_BOOLEAN);
                });

                $fillable = (new MasterPromotionDetailProduct())->getFillable();
                $data = $this->filterAndMap($filteredProducts, $fillable);
                
                if (!empty($data)) {
                    MasterPromotionDetailProduct::upsert($data, ['prp_code'], array_diff($fillable, ['prp_code', 'pdl_code', 'prm_code']));
                    $results['products'] = count($data);
                }
            }

            // 4. Sync Routes (Delete and Re-insert)
            if (!empty($routesData)) {
                $mappedRoutes = $this->filterAndMap($routesData, (new MasterPromotionRoute())->getFillable());
                $prmCodes = collect($mappedRoutes)->pluck('prm_code')->unique()->toArray();
                MasterPromotionRoute::whereIn('prm_code', $prmCodes)->delete();
                
                MasterPromotionRoute::insert($mappedRoutes);
                $results['routes'] = count($mappedRoutes);
            }

            // 5. Sync Teams (Delete and Re-insert)
            if (!empty($teamsData)) {
                $mappedTeams = $this->filterAndMap($teamsData, (new MasterPromotionTeam())->getFillable());
                $prmCodes = collect($mappedTeams)->pluck('prm_code')->unique()->toArray();
                MasterPromotionTeam::whereIn('prm_code', $prmCodes)->delete();
                
                MasterPromotionTeam::insert($mappedTeams);
                $results['teams'] = count($mappedTeams);
            }

            DB::commit();
            Log::channel('single')->info("=== ESPEJO DE PROMOCIONES COMPLETADO EXITOSAMENTE ===");

        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('single')->error("Error en Espejo de Promociones: " . $e->getMessage() . " en " . $e->getFile() . ":" . $e->getLine());
            $results['errors'][] = $e->getMessage();
        }

        return $results;
    }

    /**
     * Filtra los datos para que solo contengan campos del fillable
     * y mapea camelCase a snake_case si es necesario.
     */
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
                    // Limpieza básica de strings (algunos decimales vienen con espacios)
                    if (is_string($value)) {
                        $value = trim($value);
                        if ($value === "") $value = null;
                    }
                    
                    // Manejo de booleanos que vienen como true/false pero en DB son string/int
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
