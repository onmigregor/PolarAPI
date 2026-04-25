<?php

namespace Modules\ProductsPrice\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\ProductsPrice\Actions\MasterProductsPriceBulkSyncAction;
use Illuminate\Http\JsonResponse;

class ProductsPriceController extends Controller
{
    public function sync(Request $request, MasterProductsPriceBulkSyncAction $action): JsonResponse
    {
        $data = $request->input('data', []);
        
        if (empty($data)) {
            return response()->json([
                'success' => false,
                'message' => 'No data provided for price synchronization',
            ], 400);
        }

        $result = $action->execute($data);

        return response()->json([
            'success' => true,
            'message' => 'Products Price synchronization completed',
            'data' => $result,
        ]);
    }
}
