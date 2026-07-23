<?php

namespace Modules\Report\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Report\DataTransferObjects\BulkImportLogFilterData;
use Modules\Report\Actions\FetchBulkImportLogsAction;
use Modules\Report\Actions\RetryBulkImportProceduresAction;
use Illuminate\Support\Facades\Log;

class BulkImportLogApiController extends Controller
{
    /**
     * Devuelve el listado paginado y filtrado de bulk_import_logs en formato JSON.
     */
    public function index(Request $request, FetchBulkImportLogsAction $action): JsonResponse
    {
        try {
            $filterData = BulkImportLogFilterData::fromRequest($request);
            $paginator = $action->execute($filterData);

            return response()->json([
                'success' => true,
                'data' => $paginator->items(),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'from' => $paginator->firstItem(),
                    'to' => $paginator->lastItem(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error("BulkImportLogApiController index Error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener reporte de cargas: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Re-ejecuta la sincronización y procedimientos de inventario para una carga específica.
     */
    public function retryProcedures(int $id, RetryBulkImportProceduresAction $action): JsonResponse
    {
        try {
            $result = $action->execute($id);

            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
                'data' => $result['data'] ?? null,
            ], $result['success'] ? 200 : 400);

        } catch (\Exception $e) {
            Log::error("BulkImportLogApiController retryProcedures Error: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al re-ejecutar procedimientos: ' . $e->getMessage(),
            ], 500);
        }
    }
}
