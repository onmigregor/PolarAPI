<?php

namespace Modules\CompanyRoute\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\CompanyRoute\Actions\CompanyRouteDeleteAction;
use Modules\CompanyRoute\Actions\CompanyRouteListAction;
use Modules\CompanyRoute\Actions\CompanyRouteListAllAction;
use Modules\CompanyRoute\Actions\CompanyRouteStoreAction;
use Modules\CompanyRoute\Actions\CompanyRouteUpdateAction;
use Modules\CompanyRoute\DataTransferObjects\CompanyRouteData;
use Modules\CompanyRoute\Http\Requests\CompanyRouteStoreRequest;
use Modules\CompanyRoute\Http\Requests\CompanyRouteUpdateRequest;
use Modules\CompanyRoute\Http\Resources\CompanyRouteResource;
use Modules\CompanyRoute\Models\CompanyRoute;
use App\Traits\ApiResponse;

class CompanyRouteController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected CompanyRouteListAction $companyRouteListAction,
        protected CompanyRouteListAllAction $companyRouteListAllAction,
        protected CompanyRouteStoreAction $companyRouteStoreAction,
        protected CompanyRouteUpdateAction $companyRouteUpdateAction,
        protected CompanyRouteDeleteAction $companyRouteDeleteAction
    ) {}

    public function index(Request $request): JsonResponse
    {
        $companyRoutes = $this->companyRouteListAction->execute(
            $request->only(['search', 'region_id']),
            $request->input('per_page', 15)
        );

        return $this->success(
            CompanyRouteResource::collection($companyRoutes),
            'Company Routes retrieved successfully',
            200,
            [
                'total' => $companyRoutes->total(),
                'per_page' => $companyRoutes->perPage(),
                'current_page' => $companyRoutes->currentPage(),
                'last_page' => $companyRoutes->lastPage(),
            ]
        );
    }

    public function listAll(): JsonResponse
    {
        $companyRoutes = $this->companyRouteListAllAction->execute();

        return $this->success(CompanyRouteResource::collection($companyRoutes), 'All company routes retrieved successfully');
    }

    public function store(CompanyRouteStoreRequest $request): JsonResponse
    {
        $dto = CompanyRouteData::fromRequest($request);
        $companyRoute = $this->companyRouteStoreAction->execute($dto);

        return $this->success(
            new CompanyRouteResource($companyRoute->load('region')),
            'Company Route created successfully',
            201
        );
    }

    public function show(CompanyRoute $companyRoute): JsonResponse
    {
        return $this->success(new CompanyRouteResource($companyRoute->load('region')), 'Company Route retrieved successfully');
    }

    public function update(CompanyRouteUpdateRequest $request, CompanyRoute $companyRoute): JsonResponse
    {
        $dto = CompanyRouteData::fromRequest($request);
        $updatedCompanyRoute = $this->companyRouteUpdateAction->execute($companyRoute, $dto);

        return $this->success(
            new CompanyRouteResource($updatedCompanyRoute->load('region')),
            'Company Route updated successfully'
        );
    }

    public function destroy(CompanyRoute $companyRoute): JsonResponse
    {
        $this->companyRouteDeleteAction->execute($companyRoute);

        return $this->success(null, 'Company Route deleted successfully');
    }
}
