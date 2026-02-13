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
                // Eliminar clientes que se sincronizaron incorrectamente (EMILINADO = typo de ELIMINADO)
                MasterClient::where('company_route_id', $companyRoute->id)
                    ->where(function ($q) {
                        $q->whereRaw("UPPER(ruta) LIKE ?", ['%ELIMINADO%'])
                            ->orWhereRaw("UPPER(ruta) LIKE ?", ['%EMILINADO%']);
                    })
                    ->delete();

                Config::set('database.connections.tenant.database', $companyRoute->db_name);
                DB::purge('tenant');

                $clients = ExtClient::on('tenant')
                    ->select('IdCliente', 'Cliente', 'Ruta')
                    ->whereRaw("UPPER(Ruta) NOT LIKE ?", ['%ELIMINADO%'])
                    ->whereRaw("UPPER(Ruta) NOT LIKE ?", ['%EMILINADO%'])
                    ->get();

                foreach ($clients as $client) {
                    MasterClient::updateOrCreate(
                        [
                            'company_route_id' => $companyRoute->id,
                            'external_id' => $client->IdCliente,
                        ],
                        [
                            'cliente' => $client->Cliente ?? '',
                            'ruta' => $client->Ruta ?? null,
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
