<?php

namespace Modules\Analytics\Actions;

use Modules\MasterClient\Models\MasterClientPolar;

class GetClientsByRoutesAction
{
    public function execute(?array $routeIds): array
    {
        if (empty($routeIds)) {
            return [];
        }

        return MasterClientPolar::select('cus_code', 'cus_name', 'cus_business_name', 'company_route_id')
            ->whereIn('company_route_id', $routeIds)
            ->get()
            ->map(fn($client) => [
                'id' => $client->cus_code,
                'name' => $client->cus_business_name ?: ($client->cus_name ?: ''),
                'company_route_id' => $client->company_route_id,
            ])
            ->sortBy('name')
            ->values()
            ->toArray();
    }
}
