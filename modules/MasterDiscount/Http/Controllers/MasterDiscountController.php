<?php

namespace Modules\MasterDiscount\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\MasterDiscount\Actions\MasterDiscountAction;
use Modules\MasterDiscount\Actions\SyncDiscountsToClientsAction;

class MasterDiscountController extends Controller
{
    public function syncPolar(
        Request $request,
        MasterDiscountAction $syncAction,
        SyncDiscountsToClientsAction $pushAction
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
            $errDetail = is_array($syncResult['errors']) ? implode('; ', $syncResult['errors']) : (string)$syncResult['errors'];
            return response()->json([
                'success' => false,
                'message' => 'Error durante la sincronización HUB: ' . $errDetail,
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
                ? 'Sincronización completa: HUB actualizado y descuentos distribuidos a Tenants.' 
                : 'Sincronización con advertencias: HUB actualizado pero ocurrieron errores al distribuir descuentos a algunos Tenants.',
            'data' => [
                'hub_sync'    => $syncResult,
                'tenant_push' => $pushResult,
            ],
        ], $isSuccess ? 200 : 207);
    }
}
