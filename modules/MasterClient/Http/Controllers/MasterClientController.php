<?php
declare(strict_types=1);

namespace Modules\MasterClient\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\MasterClient\Actions\SyncMasterClientsAction;

class MasterClientController extends Controller
{
    public function sync(SyncMasterClientsAction $action): JsonResponse
    {
        $result = $action->execute();

        return response()->json([
            'success' => true,
            'message' => 'Client synchronization completed',
            'data' => $result,
        ]);
    }

    public function syncPolar(\Illuminate\Http\Request $request, \Modules\MasterClient\Actions\MasterClientBulkSyncAction $action): JsonResponse
    {
        $data = $request->input('data', []);
        $result = $action->execute($data);

        return response()->json([
            'success' => true,
            'message' => 'Polar Client synchronization completed',
            'data' => $result,
        ]);
    }
}
