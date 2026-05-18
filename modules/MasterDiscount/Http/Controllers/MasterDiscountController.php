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
            return response()->json([
                'success' => false,
                'message' => 'Error during HUB synchronization',
                'errors' => $syncResult['errors'],
            ], 500);
        }

        // 2. Distribución: HUB → Tenants
        $pushResult = $pushAction->execute();

        return response()->json([
            'success' => true,
            'message' => 'Sincronización completa: HUB actualizado y descuentos distribuidos a Tenants.',
            'data' => [
                'hub_sync'    => $syncResult,
                'tenant_push' => $pushResult,
            ],
        ]);
    }
}
