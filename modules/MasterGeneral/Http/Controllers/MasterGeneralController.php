<?php
declare(strict_types=1);

namespace Modules\MasterGeneral\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\MasterGeneral\Actions\MasterGeneralBulkSyncAction;

class MasterGeneralController extends Controller
{
    public function syncPolar(Request $request, MasterGeneralBulkSyncAction $action): JsonResponse
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
            'message' => 'Motives synchronization completed',
            'data' => $result,
        ]);
    }
}
