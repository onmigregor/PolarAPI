<?php

namespace Modules\Analytics\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Analytics\Actions\GetFiltersAction;
use Modules\Analytics\Actions\SyncMasterProductsAction;
use Modules\Analytics\Actions\GetSalesByProductAction;
use Modules\Analytics\Actions\GetTopProductsAction;
use Modules\Analytics\Actions\GetSalesTrendAction;
use Modules\Analytics\Actions\GetDailySalesTrendAction;
use Modules\Analytics\Actions\GetSalesByRouteAction;
use Modules\Analytics\Actions\GetTopGroupsByLitersAction;
use Modules\Analytics\Actions\GetTopGroupsByKilosAction;
use Modules\Analytics\Http\Requests\ReportFilterRequest;
use Modules\Analytics\DataTransferObjects\ReportFilterData;

class AnalyticsController extends Controller
{
    public function getFilters(GetFiltersAction $action): JsonResponse
    {
        $filters = $action->execute();

        return response()->json([
            'success' => true,
            'data' => $filters,
        ]);
    }

    public function syncProducts(SyncMasterProductsAction $action): JsonResponse
    {
        $result = $action->execute();

        return response()->json([
            'success' => true,
            'message' => "Synced {$result['synced_count']} products from {$result['clients_processed']} clients",
            'data' => $result,
        ]);
    }

    public function salesByProduct(
        ReportFilterRequest $request,
        GetSalesByProductAction $action
    ): JsonResponse {
        $filters = ReportFilterData::fromRequest($request->validated());
        $result = $action->execute($filters);

        return response()->json([
            'success' => true,
            'data' => $result['data'],
            'meta' => [
                'clients_queried' => $result['clients_queried'],
                'errors' => $result['errors'],
            ],
        ]);
    }

    public function topProducts(
        ReportFilterRequest $request,
        GetTopProductsAction $action
    ): JsonResponse {
        $filters = ReportFilterData::fromRequest($request->validated());
        $limit = $request->input('limit', 10);
        $result = $action->execute($filters, $limit);

        return response()->json([
            'success' => true,
            'data' => $result['data'],
            'meta' => [
                'clients_queried' => $result['clients_queried'],
                'errors' => $result['errors'],
            ],
        ]);
    }

    public function salesTrend(
        ReportFilterRequest $request,
        GetSalesTrendAction $action
    ): JsonResponse {
        $filters = ReportFilterData::fromRequest($request->validated());
        $result = $action->execute($filters);

        return response()->json([
            'success' => true,
            'data' => $result['data'],
            'meta' => [
                'clients_queried' => $result['clients_queried'],
                'errors' => $result['errors'],
            ],
        ]);
    }

    public function salesByRoute(
        ReportFilterRequest $request,
        GetSalesByRouteAction $action
    ): JsonResponse {
        $filters = ReportFilterData::fromRequest($request->validated());
        $result = $action->execute($filters);

        return response()->json([
            'success' => true,
            'data' => $result['data'],
            'meta' => [
                'clients_queried' => $result['clients_queried'],
                'errors' => $result['errors'],
            ],
        ]);
    }

    public function dailySalesTrend(
        ReportFilterRequest $request,
        GetDailySalesTrendAction $action
    ): JsonResponse {
        $filters = ReportFilterData::fromRequest($request->validated());
        $result = $action->execute($filters);

        return response()->json([
            'success' => true,
            'data' => $result['data'],
            'meta' => [
                'clients_queried' => $result['clients_queried'],
                'errors' => $result['errors'],
            ],
        ]);
    }

    public function topGroupsByLiters(
        ReportFilterRequest $request,
        GetTopGroupsByLitersAction $action
    ): JsonResponse {
        $filters = ReportFilterData::fromRequest($request->validated());
        $limit = $request->input('limit', 15);
        $result = $action->execute($filters, $limit);

        return response()->json([
            'success' => true,
            'data' => $result['data'],
            'meta' => [
                'clients_queried' => $result['clients_queried'],
                'errors' => $result['errors'],
            ],
        ]);
    }

    public function topGroupsByKilos(
        ReportFilterRequest $request,
        GetTopGroupsByKilosAction $action
    ): JsonResponse {
        $filters = ReportFilterData::fromRequest($request->validated());
        $limit = $request->input('limit', 15);
        $result = $action->execute($filters, $limit);

        return response()->json([
            'success' => true,
            'data' => $result['data'],
            'meta' => [
                'clients_queried' => $result['clients_queried'],
                'errors' => $result['errors'],
            ],
        ]);
    }
}
