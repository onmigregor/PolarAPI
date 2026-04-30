<?php

namespace Modules\MasterPromotion\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\MasterPromotion\Actions\MasterPromotionBulkSyncAction;

class MasterPromotionController extends Controller
{
    public function syncPolar(Request $request, MasterPromotionBulkSyncAction $action): JsonResponse
    {
        $payload = $request->all();

        if (empty($payload)) {
            return response()->json([
                'success' => false,
                'message' => 'No data provided for synchronization',
            ], 400);
        }

        $result = $action->execute($payload);

        if (!empty($result['errors'])) {
            return response()->json([
                'success' => false,
                'message' => 'Error during synchronization',
                'errors' => $result['errors'],
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Promotions mirror synchronization completed',
            'data' => $result,
        ]);
    }
}
