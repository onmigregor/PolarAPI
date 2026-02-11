<?php

namespace Modules\Analytics\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Analytics\Actions\GetFiltersAction;
use Modules\Analytics\Actions\SyncMasterProductsAction;

class AnalyticsController extends Controller
{
    public function getFilters(GetFiltersAction $action): JsonResponse
    {
        $filters = $action->execute();

        return response()->json([
            'success' => true,
            'data' => $filters,
        ]);
    }

    public function syncProducts(SyncMasterProductsAction $action): JsonResponse
    {
        $result = $action->execute();

        return response()->json([
            'success' => true,
            'message' => "Synced {$result['synced_count']} products from {$result['clients_processed']} clients",
            'data' => $result,
        ]);
    }
}
