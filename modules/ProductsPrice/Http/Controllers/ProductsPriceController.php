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
        $isSuccess = $result['success'] ?? false;

        return response()->json([
            'success' => $isSuccess,
            'message' => $isSuccess ? 'Sincronización de precios completada correctamente.' : 'La sincronización de precios finalizó con errores en uno o varios tenants.',
            'data' => $result,
        ], $isSuccess ? 200 : 207);
    }
}
