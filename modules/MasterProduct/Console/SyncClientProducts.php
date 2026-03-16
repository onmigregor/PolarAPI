<?php

namespace Modules\MasterProduct\Console;

use Illuminate\Console\Command;
use Modules\MasterProduct\Actions\SyncClientProductsAction;

class SyncClientProducts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'products:sync-clients';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sincronizar datos básicos (SKU, nombre) desde BDs de clientes al catálogo maestro';

    /**
     * Execute the console command.
     */
    public function handle(SyncClientProductsAction $action)
    {
        $this->info('Iniciando sincronización de productos desde CLIENTES (tenants)...');

        $result = $action->execute();

        $this->info("Sincronización completada para {$result['clients_processed']} clientes:");
        $this->line("- Productos creados nuevos: {$result['created_count']}");
        $this->line("- Productos actualizados:   {$result['updated_count']}");

        if (!empty($result['errors'])) {
            $this->warn('Ocurrieron errores durante la sincronización:');
            foreach ($result['errors'] as $error) {
                $this->error("{$error['client']}: {$error['error']}");
            }
        }

        return Command::SUCCESS;
    }
}
