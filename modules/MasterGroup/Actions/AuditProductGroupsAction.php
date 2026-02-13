<?php
declare(strict_types=1);

namespace Modules\MasterGroup\Actions;

use Modules\CompanyRoute\Models\CompanyRoute;
use Modules\Analytics\Models\External\ExtProduct;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AuditProductGroupsAction
{
    public function execute(): array
    {
        Log::channel('auditoria')->info("================ AUDITORÍA INICIADA: PRODUCTOS SIN GRUPO ================");

        $clients = CompanyRoute::where('is_active', true)->get();
        $totalOrphans = 0;
        $report = [];

        foreach ($clients as $client) {
            try {
                // Configure tenant connection
                Config::set('database.connections.tenant.database', $client->db_name);
                DB::purge('tenant');

                // Find products without assigned group
                $orphanProducts = ExtProduct::on('tenant')
                    ->where(function ($query) {
                        $query->whereNull('grupo')
                              ->orWhere('grupo', '');
                    })
                    ->where('producto_activo', 1)
                    ->get(['codigoSKU', 'producto']);

                if ($orphanProducts->isNotEmpty()) {
                    $totalOrphans += $orphanProducts->count();

                    $this->logOrphans($client->name, $orphanProducts);

                    $report[] = [
                        'client' => $client->name,
                        'orphan_count' => $orphanProducts->count(),
                    ];
                } else {
                    Log::channel('auditoria')->info("Cliente {$client->name}: No se encontraron productos huérfanos.");
                }
            } catch (\Exception $e) {
                Log::channel('auditoria')->error("Error al auditar el cliente {$client->name}: {$e->getMessage()}");
            }
        }

        Log::channel('auditoria')->info("RESUMEN: Auditoría completada. Total de productos huérfanos: {$totalOrphans}");
        Log::channel('auditoria')->info("=========================================================================");

        return [
            'total_orphans' => $totalOrphans,
            'report' => $report,
        ];
    }

    private function logOrphans(string $clientName, $products): void
    {
        Log::channel('auditoria')->info("--- Auditoría: Productos sin grupo para el cliente: {$clientName} ---");

        foreach ($products as $product) {
            Log::channel('auditoria')->warning("Producto Huérfano: [{$product->codigoSKU}] {$product->producto}");
        }

        Log::channel('auditoria')->info("------------------------------------------------------------------");
    }
}
