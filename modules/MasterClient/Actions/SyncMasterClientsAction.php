<?php
declare(strict_types=1);

namespace Modules\MasterClient\Actions;

use Modules\CompanyRoute\Models\CompanyRoute;
use Modules\MasterClient\Models\MasterClient;
use Modules\MasterClient\Models\External\ExtClient;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncMasterClientsAction
{
    public function execute(): array
    {
        $companyRoutes = CompanyRoute::where('is_active', true)->get();
        $syncedCount = 0;
        $errors = [];

        foreach ($companyRoutes as $companyRoute) {
            try {
                Config::set('database.connections.tenant.database', $companyRoute->db_name);
                DB::purge('tenant');

                $clients = ExtClient::on('tenant')
                    ->select('IdCliente', 'cep', 'Cliente', 'Ruta')
                    ->whereRaw("UPPER(Ruta) NOT LIKE ?", ['%ELIMINADO%'])
                    ->whereRaw("UPPER(Ruta) NOT LIKE ?", ['%EMILINADO%'])
                    ->get();

                foreach ($clients as $client) {
                    if (!$client->cep) {
                        continue;
                    }

                    MasterClient::updateOrCreate(
                        [
                            'cus_code' => ltrim((string)$client->cep, '0'),
                        ],
                        [
                            'company_route_id' => $companyRoute->id,
                            'cus_name' => $client->Cliente ?? '',
                            'cus_business_name' => $client->Cliente ?? '',
                            'registered_at_tenant' => now(),
                        ]
                    );
                    $syncedCount++;
                }
            } catch (\Exception $e) {
                Log::error("Error syncing clients for {$companyRoute->name}: " . $e->getMessage());
                $errors[] = [
                    'company_route' => $companyRoute->name,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return [
            'synced_count' => $syncedCount,
            'clients_processed' => $companyRoutes->count(),
            'errors' => $errors,
        ];
    }
}
