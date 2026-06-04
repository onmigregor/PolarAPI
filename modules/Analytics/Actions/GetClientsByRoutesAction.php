<?php

namespace Modules\Analytics\Actions;

use Modules\MasterClient\Models\MasterClient;

class GetClientsByRoutesAction
{
    public function execute(?array $routeIds): array
    {
        if (empty($routeIds)) {
            return [];
        }

        return MasterClient::select('cep', 'cliente', 'company_route_id')
            ->whereIn('company_route_id', $routeIds)
            ->orderBy('cliente')
            ->get()
            ->map(fn($client) => [
                'id' => $client->cep, // cep = IdCliente in tenant DB
                'name' => $client->cliente,
                'company_route_id' => $client->company_route_id,
            ])
            ->toArray();
    }
}
