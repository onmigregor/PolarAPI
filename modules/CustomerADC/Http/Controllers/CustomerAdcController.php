<?php

namespace Modules\CustomerADC\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\CustomerADC\Actions\MasterCustomerAdcBulkSyncAction;
use Illuminate\Http\JsonResponse;

class CustomerAdcController extends Controller
{
    public function sync(Request $request, MasterCustomerAdcBulkSyncAction $action): JsonResponse
    {
        $data = $request->input('data', []);
        
        if (empty($data)) {
            return response()->json([
                'success' => false,
                'message' => 'No data provided for synchronization',
            ], 400);
        }

        $result = $action->execute($data);

        return response()->json([
            'success' => true,
            'message' => 'Customer ADC synchronization completed',
            'data' => $result,
        ]);
    }
}
