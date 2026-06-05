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
        $response = $this->getPaginatedAction->execute(
            $request->only(['query', 'tp1_code', 'tp2_code', 'cit_code', 'has_cep']),
            $request->input('per_page') ? (int)$request->input('per_page') : null
        );

        return $this->success(
            MasterClientResource::collection($response),
            'Master clients retrieved successfully'
        );
    }

    public function getFilters(Request $request): JsonResponse
    {
        $filters = $this->getFiltersAction->execute(
            $request->only(['tp1_code', 'tp2_code'])
        );

        return $this->success($filters, 'Master client filter options retrieved successfully');
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
            
            $result = $action->execute(
                $data, 
                $branches, 
                $segments, 
                $pools, 
                $customerPools, 
                $customerRoutes, 
                $customerPrices, 
                $customerFrequencies
            );

            return response()->json([
                'success' => true,
                'message' => 'Polar Client synchronization completed',
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Error in syncPolar: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }
}
