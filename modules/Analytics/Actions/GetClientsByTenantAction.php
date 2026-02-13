<?php

namespace Modules\Analytics\Actions;

use Modules\MasterClient\Models\MasterClient;
use Illuminate\Support\Facades\DB;

class GetClientsByTenantAction
{
    public function execute(): array
    {
        $rows = DB::table('master_clients')
            ->join('company_routes', 'company_routes.id', '=', 'master_clients.company_route_id')
            ->select(
                'company_routes.name as company_route_name',
                DB::raw('COUNT(master_clients.id) as total_clients')
            )
            ->groupBy('company_routes.id', 'company_routes.name')
            ->orderByDesc(DB::raw('COUNT(master_clients.id)'))
            ->get();

        $data = $rows->map(function ($row) {
            return [
                'company_route_name' => $row->company_route_name,
                'total_clients' => (int) $row->total_clients,
            ];
        })->toArray();

        $totalClients = array_sum(array_column($data, 'total_clients'));

        return [
            'data' => $data,
            'meta' => [
                'total_clients' => $totalClients,
                'tenants_count' => count($data),
            ],
        ];
    }
}
