<?php

namespace Modules\MasterPromotion\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\MasterPromotion\Actions\MasterPromotionBulkSyncAction;
use Modules\MasterPromotion\Actions\SyncPromotionsToClientsAction;

class MasterPromotionController extends Controller
{
    public function syncPolar(
        Request $request,
        MasterPromotionBulkSyncAction $syncAction,
        SyncPromotionsToClientsAction $pushAction
    ): JsonResponse {
        $payload = $request->all();

        if (empty($payload)) {
            return response()->json([
                'success' => false,
                'message' => 'No data provided for synchronization',
            ], 400);
        }

        // 1. Espejo: Admin → HUB
        $syncResult = $syncAction->execute($payload);

        if (!empty($syncResult['errors'])) {
            return response()->json([
                'success' => false,
                'message' => 'Error during HUB synchronization',
                'errors' => $syncResult['errors'],
            ], 500);
        }

        // 2. Distribución: HUB → Tenants
        $pushResult = $pushAction->execute();
        $hasPushErrors = !empty($pushResult['errors']);
        $isSuccess = !$hasPushErrors;

        return response()->json([
            'success' => $isSuccess,
            'message' => $isSuccess 
                ? 'Sincronización completa: HUB actualizado y promociones distribuidas a Tenants.' 
                : 'Sincronización con advertencias: HUB actualizado pero ocurrieron errores al distribuir promociones a algunos Tenants.',
            'data' => [
                'hub_sync'    => $syncResult,
                'tenant_push' => $pushResult,
            ],
        ], $isSuccess ? 200 : 207);
    }
}

