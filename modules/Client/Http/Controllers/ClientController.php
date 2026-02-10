<?php

namespace Modules\Client\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Client\Actions\ClientDeleteAction;
use Modules\Client\Actions\ClientListAction;
use Modules\Client\Actions\ClientListAllAction;
use Modules\Client\Actions\ClientStoreAction;
use Modules\Client\Actions\ClientUpdateAction;
use Modules\Client\DataTransferObjects\ClientData;
use Modules\Client\Http\Requests\ClientStoreRequest;
use Modules\Client\Http\Requests\ClientUpdateRequest;
use Modules\Client\Http\Resources\ClientResource;
use Modules\Client\Models\Client;
use App\Traits\ApiResponse;

class ClientController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ClientListAction $clientListAction,
        protected ClientListAllAction $clientListAllAction,
        protected ClientStoreAction $clientStoreAction,
        protected ClientUpdateAction $clientUpdateAction,
        protected ClientDeleteAction $clientDeleteAction
    ) {}

    public function index(Request $request): JsonResponse
    {
        $clients = $this->clientListAction->execute(
            $request->only(['search', 'region_id']),
            $request->input('per_page', 15)
        );

        return $this->success(
            ClientResource::collection($clients),
            'Clients retrieved successfully',
            200,
            [
                'total' => $clients->total(),
                'per_page' => $clients->perPage(),
                'current_page' => $clients->currentPage(),
                'last_page' => $clients->lastPage(),
            ]
        );
    }

    public function listAll(): JsonResponse
    {
        $clients = $this->clientListAllAction->execute();
        return $this->success(ClientResource::collection($clients), 'All clients retrieved successfully');
    }

    public function store(ClientStoreRequest $request): JsonResponse
    {
        $dto = ClientData::fromRequest($request);
        $client = $this->clientStoreAction->execute($dto);

        return $this->success(
            new ClientResource($client->load('region')),
            'Client created successfully',
            201
        );
    }

    public function show(Client $client): JsonResponse
    {
        return $this->success(new ClientResource($client->load('region')), 'Client retrieved successfully');
    }

    public function update(ClientUpdateRequest $request, Client $client): JsonResponse
    {
        $dto = ClientData::fromRequest($request);
        $updatedClient = $this->clientUpdateAction->execute($client, $dto);

        return $this->success(
            new ClientResource($updatedClient->load('region')),
            'Client updated successfully'
        );
    }

    public function destroy(Client $client): JsonResponse
    {
        $this->clientDeleteAction->execute($client);
        return $this->success(null, 'Client deleted successfully');
    }
}
