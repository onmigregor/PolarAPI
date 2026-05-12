<?php

namespace Modules\MasterProduct\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\MasterProduct\Actions\EnsureProductClassificationColumnsExistAction;
use Modules\MasterProduct\Actions\SyncMasterProductsToTenantsAction;
use Modules\CompanyRoute\Models\CompanyRoute;

class SyncTenantProductsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $tenantId;

    /**
     * El trabajo puede tardar bastante, así que le damos tiempo de sobra.
     */
    public $timeout = 600; // 10 minutos por tenant

    public function __construct($tenantId)
    {
        $this->tenantId = $tenantId;
    }

    public function handle()
    {
        $tenant = CompanyRoute::find($this->tenantId);

        if (!$tenant) {
            return;
        }

        Log::info("Job: Iniciando sincronización total para Tenant {$tenant->name} ({$tenant->db_name})");

        // FASE 1: Asegurar Estructura
        // (Aunque sea redundante, es mejor verificar por si acaso)
        // Nota: Reutilizamos la lógica pero solo para este tenantdb
        $this->ensureInfra($tenant);

        // FASE 2: Sincronizar Datos
        $syncAction = new SyncMasterProductsToTenantsAction();
        $syncAction->execute($tenant->db_name);

        Log::info("Job: Sincronización completada con éxito para Tenant {$tenant->name}");
    }

    protected function ensureInfra($tenant)
    {
        // Lógica simplificada para un solo tenant dentro del Job
        try {
            config(['database.connections.tenant.database' => $tenant->db_name]);
            \Illuminate\Support\Facades\DB::purge('tenant');

            if (\Illuminate\Support\Facades\Schema::connection('tenant')->hasTable('productos')) {
                $neededColumns = [
                    'class1', 'class2', 'class3', 'class4',
                    'unt_code', 'familia', 'segmento',
                    'proshortname', 'probarcode', 'bomcode',
                    'proreturnallowed', 'prodamegereturnsallowed',
                    'proavailableforsale', 'procustomerinventoryallowed'
                ];
                foreach ($neededColumns as $col) {
                    if (!\Illuminate\Support\Facades\Schema::connection('tenant')->hasColumn('productos', $col)) {
                        \Illuminate\Support\Facades\Schema::connection('tenant')->table('productos', function ($table) use ($col) {
                            $table->string($col, 100)->nullable();
                        });
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error("Job Infra Error en {$tenant->name}: " . $e->getMessage());
        }
    }
}
