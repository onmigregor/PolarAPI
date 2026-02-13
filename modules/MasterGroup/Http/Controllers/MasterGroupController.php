<?php
declare(strict_types=1);

namespace Modules\MasterGroup\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\MasterGroup\Actions\SyncMasterGroupsAction;
use Modules\MasterGroup\Actions\AuditProductGroupsAction;

class MasterGroupController extends Controller
{
    public function sync(SyncMasterGroupsAction $action): JsonResponse
    {
        $result = $action->execute();

        return response()->json([
            'success' => true,
            'message' => 'Group synchronization completed',
            'data' => $result,
        ]);
    }

    public function audit(AuditProductGroupsAction $action): JsonResponse
    {
        $result = $action->execute();

        return response()->json([
            'success' => true,
            'message' => 'Product group audit completed',
            'data' => $result,
        ]);
    }
}
