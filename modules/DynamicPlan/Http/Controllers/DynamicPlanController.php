<?php

namespace Modules\DynamicPlan\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\DynamicPlan\Actions\MasterDynamicPlanBulkSyncAction;

class DynamicPlanController extends Controller
{
    public function syncPolar(Request $request, MasterDynamicPlanBulkSyncAction $action): JsonResponse
    {
        $data = $request->input('data', []);
        
        if (empty($data)) {
            return response()->json([
                'success' => false,
                'message' => 'No data provided for synchronization',
            ], 400);
        }

        $result = $action->execute($data);
        $hasErrors = !empty($result['errors']);
        $isSuccess = !$hasErrors;

        return response()->json([
            'success' => $isSuccess,
            'message' => $isSuccess ? 'Sincronización de planes dinámicos completada correctamente.' : 'La sincronización de planes dinámicos finalizó con errores en uno o varios tenants.',
            'data' => $result,
        ], $isSuccess ? 200 : 207);
    }
}
