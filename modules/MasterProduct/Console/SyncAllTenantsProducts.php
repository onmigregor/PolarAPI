<?php

namespace Modules\MasterProduct\Console;

use Illuminate\Console\Command;
use Modules\CompanyRoute\Models\CompanyRoute;
use Modules\MasterProduct\Jobs\SyncTenantProductsJob;

class SyncAllTenantsProducts extends Command
{
    /**
     * El nombre y firma del comando.
     */
    protected $signature = 'products:sync-tenants {--tenant= : ID de un tenant específico}';

    /**
     * La descripción del comando.
     */
    protected $description = 'Despacha trabajos de sincronización total de productos para todos los tenants activos';

    public function handle()
    {
        $tenantId = $this->option('tenant');

        if ($tenantId) {
            $tenants = CompanyRoute::where('id', $tenantId)->get();
        } else {
            $tenants = CompanyRoute::where('is_active', true)->get();
        }

        $count = $tenants->count();
        $this->info("Despachando sincronización para {$count} vendedores...");

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        foreach ($tenants as $tenant) {
            SyncTenantProductsJob::dispatch($tenant->id);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("¡Listo! Los 300 procesos están en la cola de trabajo (Queue).");
        $this->info("Asegúrate de tener corriendo 'php artisan queue:work'.");
    }
}
