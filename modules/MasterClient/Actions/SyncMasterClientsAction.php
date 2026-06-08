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
        // Desactivar el log de consultas de la base de datos principal para evitar consumo de memoria acumulativo
        DB::connection('mysql')->disableQueryLog();

        $companyRoutes = CompanyRoute::where('is_active', true)->get();
        $syncedCount = 0;
        $errors = [];

        foreach ($companyRoutes as $companyRoute) {
            try {
                Config::set('database.connections.tenant.database', $companyRoute->db_name);
                DB::purge('tenant');
                
                // Desactivar el log de consultas para la conexión del tenant
                DB::connection('tenant')->disableQueryLog();

                // Asegurar que las columnas requeridas (incluyendo synced_to_master) existen en el tenant
                $this->ensureClientesColumnsExist('tenant');

                // Seleccionar solo los clientes que no tienen cep (creados en el tenant) y no se han sincronizado
                $clients = DB::connection('tenant')->table('clientes')
                    ->select('IdCliente', 'cep', 'Cliente', 'cus_business_name', 'Ruta', 'RIF', 'TelefonoContacto', 'email', 'tp1_code', 'TipoCliente', 'segmento')
                    ->whereRaw("UPPER(Ruta) NOT LIKE ?", ['%ELIMINADO%'])
                    ->whereRaw("UPPER(Ruta) NOT LIKE ?", ['%EMILINADO%'])
                    ->where(function($q) {
                        $q->whereNull('cep')->orWhere('cep', '');
                    })
                    ->where('synced_to_master', 0)
                    ->get();

                foreach ($clients as $client) {
                    $existingMaster = null;
                    if (!empty($client->RIF)) {
                        // Buscar si ya existe un cliente local en master con el mismo RIF para la misma ruta
                        $existingMaster = MasterClient::where('cus_tax_id1', $client->RIF)
                            ->where('company_route_id', $companyRoute->id)
                            ->where(function($q) {
                                $q->whereNull('cus_code')->orWhere('cus_code', '');
                            })
                            ->first();
                    }

                    if ($existingMaster) {
                        $existingMaster->update([
                            'cus_name' => $client->Cliente ?? $existingMaster->cus_name,
                            'cus_business_name' => $client->cus_business_name ?? ($client->Cliente ?? $existingMaster->cus_business_name),
                            'tp1_code' => $client->tp1_code ?? $existingMaster->tp1_code,
                            'tp2_code' => $client->TipoCliente ?? $existingMaster->tp2_code,
                            'cit_code' => $client->segmento ?? $existingMaster->cit_code,
                            'cus_phone' => $client->TelefonoContacto ?? $existingMaster->cus_phone,
                            'cus_email' => $client->email ?? $existingMaster->cus_email,
                            'registered_at_tenant' => now(),
                        ]);
                    } else {
                        MasterClient::create([
                            'cus_code' => null, // Guardar como NULL para permitir unicidad de nulos
                            'company_route_id' => $companyRoute->id,
                            'cus_name' => $client->Cliente ?? '',
                            'cus_business_name' => $client->cus_business_name ?? ($client->Cliente ?? ''),
                            'tp1_code' => $client->tp1_code ?? null,
                            'tp2_code' => $client->TipoCliente ?? null,
                            'cit_code' => $client->segmento ?? null,
                            'cus_tax_id1' => $client->RIF ?? null,
                            'cus_phone' => $client->TelefonoContacto ?? null,
                            'cus_email' => $client->email ?? null,
                            'registered_at_tenant' => now(),
                        ]);
                    }

                    // Marcar como sincronizado a nivel de tenant
                    DB::connection('tenant')->table('clientes')
                        ->where('IdCliente', $client->IdCliente)
                        ->update(['synced_to_master' => 1]);

                    $syncedCount++;
                }

                unset($clients); // Liberar memoria de la colección de clientes

            } catch (\Exception $e) {
                Log::error("Error syncing clients for {$companyRoute->name}: " . $e->getMessage());
                $errors[] = [
                    'company_route' => $companyRoute->name,
                    'error' => $e->getMessage(),
                ];
            } finally {
                // Desconectar explícitamente para liberar el socket de conexión a base de datos y memoria
                DB::disconnect('tenant');
            }
        }

        return [
            'synced_count' => $syncedCount,
            'clients_processed' => $companyRoutes->count(),
            'errors' => $errors,
        ];
    }

    private function ensureClientesColumnsExist(string $connection): void
    {
        try {
            $columns = DB::connection($connection)->select("SHOW COLUMNS FROM clientes");
            $existingColumns = array_column($columns, 'Field');

            $toAdd = [
                'synced_to_master'   => 'TINYINT(1) NOT NULL DEFAULT 0',
                'cus_business_name'  => 'VARCHAR(255) DEFAULT NULL',
                'tp1_code'           => 'VARCHAR(20) DEFAULT NULL',
            ];

            foreach ($toAdd as $col => $definition) {
                if (!in_array($col, $existingColumns)) {
                    Log::info("SyncMasterClientsAction: Adding column {$col} to table clientes on connection {$connection}");
                    DB::connection($connection)->statement("ALTER TABLE clientes ADD COLUMN `{$col}` {$definition}");
                }
            }
        } catch (\Exception $e) {
            Log::warning("SyncMasterClientsAction: Error ensuring column structure on {$connection}: " . $e->getMessage());
        }
    }
}
