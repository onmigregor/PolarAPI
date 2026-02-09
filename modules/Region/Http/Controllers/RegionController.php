<?php

namespace Modules\Region\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Modules\Region\Actions\RegionDeleteAction;
use Modules\Region\Actions\RegionGetAllAction;
use Modules\Region\Actions\RegionGetPaginatedAction;
use Modules\Region\Actions\RegionStoreAction;
use Modules\Region\Actions\RegionUpdateAction;
use Modules\Region\DataTransferObjects\RegionData;
use Modules\Region\Http\Requests\RegionRequest;
use Modules\Region\Http\Resources\RegionResource;
use Modules\Region\Models\Region;

use App\Traits\ApiResponse;
// ...

class RegionController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly RegionGetPaginatedAction $regionGetPaginatedAction,
        private readonly RegionGetAllAction $regionGetAllAction,
        private readonly RegionStoreAction $regionStoreAction,
        private readonly RegionUpdateAction $regionUpdateAction,
        private readonly RegionDeleteAction $regionDeleteAction
    ) {}

    public function index(Request $request): JsonResponse
    {
        $response = $this->regionGetPaginatedAction->execute(
            $request->only(['query']),
            $request->input('per_page')
        );

        return $this->success(RegionResource::collection($response), 'Regions retrieved successfully');
    }

    public function listAll(): JsonResponse
    {
        $response = $this->regionGetAllAction->execute();
        return $this->success(RegionResource::collection($response), 'All regions retrieved successfully');
    }

    public function store(RegionRequest $request): JsonResponse
    {
        $data = RegionData::fromRequest($request);
        $region = $this->regionStoreAction->execute($data);
        return $this->success(RegionResource::make($region), 'Region created successfully', Response::HTTP_CREATED);
    }

    public function show(Region $region): JsonResponse
    {
        return $this->success(RegionResource::make($region), 'Region retrieved successfully');
    }

    public function update(RegionRequest $request, Region $region): JsonResponse
    {
        $data = RegionData::fromRequest($request);
        $updatedRegion = $this->regionUpdateAction->execute($data, $region);
        return $this->success(RegionResource::make($updatedRegion), 'Region updated successfully');
    }

    public function destroy(Region $region): JsonResponse
    {
        $this->regionDeleteAction->execute($region);
        return $this->success(null, 'Region deleted successfully');
    }
}
