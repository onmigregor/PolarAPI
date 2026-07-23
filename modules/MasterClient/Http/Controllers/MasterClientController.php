<?php
declare(strict_types=1);

namespace Modules\MasterClient\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Modules\MasterClient\Http\Requests\MasterClientListRequest;
use Modules\MasterClient\Http\Resources\MasterClientResource;
use Modules\MasterClient\Actions\MasterClientGetPaginatedAction;
use Modules\MasterClient\Actions\MasterClientGetFiltersAction;
use Modules\MasterClient\Actions\SyncMasterClientsAction;

class MasterClientController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly MasterClientGetPaginatedAction $getPaginatedAction,
        private readonly MasterClientGetFiltersAction $getFiltersAction
    ) {}

    public function index(MasterClientListRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $paginated = $this->getPaginatedAction->execute(
            $filters,
            $request->has('per_page') ? (int)$request->input('per_page') : null
        );

        return response()->json([
            'success' => true,
            'message' => 'Master clients retrieved successfully',
            'data' => MasterClientResource::collection($paginated->items()),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ]
        ]);
    }

    public function getFilters(Request $request): JsonResponse
    {
        $filters = $this->getFiltersAction->execute(
            $request->only(['tp1_code', 'tp2_code'])
        );

        return response()->json([
            'success' => true,
            'message' => 'Master client filter options retrieved successfully',
            'data' => $filters,
        ]);
    }

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
        try {
            $data = $request->input('data', []);
            $branches = $request->input('branches', []);
            $segments = $request->input('segments', []);
            $pools = $request->input('pools', []);
            $customerPools = $request->input('customer_pools', []);
            $customerRoutes = $request->input('customer_routes', []);
            $customerPrices = $request->input('customer_prices', []);
            $customerFrequencies = $request->input('customer_frequencies', []);
            $types1 = $request->input('types1', []);
            
            $result = $action->execute(
                $data, 
                $branches, 
                $segments, 
                $pools, 
                $customerPools, 
                $customerRoutes, 
                $customerPrices, 
                $customerFrequencies,
                $types1
            );

            $hasErrors = !empty($result['errors']);
            $isSuccess = !$hasErrors;

            return response()->json([
                'success' => $isSuccess,
                'message' => $isSuccess ? 'Sincronización de clientes completada correctamente.' : 'La sincronización de clientes finalizó con errores en uno o varios tenants.',
                'data' => $result,
            ], $isSuccess ? 200 : 207);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Error in syncPolar: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }
}
