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

        try {
            DB::beginTransaction();

            // 1. Sync Promotions (Headers)
            if (!empty($payload['promotions'])) {
                $fillable = (new MasterPromotion())->getFillable();
                $data = $this->filterData($payload['promotions'], $fillable);
                MasterPromotion::upsert($data, ['prm_code'], array_diff($fillable, ['prm_code']));
                $results['promotions'] = count($data);
            }

            // 2. Sync Details
            if (!empty($payload['details'])) {
                $fillable = (new MasterPromotionDetail())->getFillable();
                $data = $this->filterData($payload['details'], $fillable);
                MasterPromotionDetail::upsert($data, ['pdl_code'], array_diff($fillable, ['pdl_code', 'prm_code']));
                $results['details'] = count($data);
            }

            // 3. Sync Products
            if (!empty($payload['products'])) {
                $fillable = (new MasterPromotionDetailProduct())->getFillable();
                $data = $this->filterData($payload['products'], $fillable);
                MasterPromotionDetailProduct::upsert($data, ['prp_code'], array_diff($fillable, ['prp_code', 'pdl_code', 'prm_code']));
                $results['products'] = count($data);
            }

            // 4. Sync Routes (Delete and Re-insert)
            if (!empty($payload['routes'])) {
                $prmCodes = collect($payload['routes'])->pluck('prm_code')->unique()->toArray();
                MasterPromotionRoute::whereIn('prm_code', $prmCodes)->delete();
                
                $fillable = (new MasterPromotionRoute())->getFillable();
                $data = $this->filterData($payload['routes'], $fillable);
                MasterPromotionRoute::insert($data);
                $results['routes'] = count($data);
            }

            // 5. Sync Teams (Delete and Re-insert)
            if (!empty($payload['teams'])) {
                $prmCodes = collect($payload['teams'])->pluck('prm_code')->unique()->toArray();
                MasterPromotionTeam::whereIn('prm_code', $prmCodes)->delete();
                
                $fillable = (new MasterPromotionTeam())->getFillable();
                $data = $this->filterData($payload['teams'], $fillable);
                MasterPromotionTeam::insert($data);
                $results['teams'] = count($data);
            }

            DB::commit();
            Log::channel('single')->info("=== ESPEJO DE PROMOCIONES COMPLETADO EXITOSAMENTE ===");

        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('single')->error("Error en Espejo de Promociones: " . $e->getMessage());
            $results['errors'][] = $e->getMessage();
        }

        return $results;
    }

    private function filterData(array $items, array $fillable): array
    {
        $now = now();
        return array_map(function ($item) use ($fillable, $now) {
            $filtered = array_intersect_key($item, array_flip($fillable));
            $filtered['created_at'] = $now;
            $filtered['updated_at'] = $now;
            return $filtered;
        }, $items);
    }
}
