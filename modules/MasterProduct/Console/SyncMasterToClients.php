<?php

namespace Modules\MasterProduct\Console;

use Illuminate\Console\Command;
use Modules\MasterProduct\Actions\SyncMasterToClientsAction;

class SyncMasterToClients extends Command
{
    protected $signature = 'products:push-to-clients';

    protected $description = 'Empuja brand, cl2_code, cl3_code y unt_code desde el catálogo maestro hacia las BDs de cada cliente/tenant';

    public function handle(SyncMasterToClientsAction $action): int
    {
        $this->info('Iniciando sincronización inversa: Maestro Polar → Clientes...');
        $this->line('(Creando columnas en tenants si no existen)');

        $result = $action->execute();

        $this->info("Sincronización completada:");
        $this->line("- Clientes procesados:     {$result['clients_processed']}");
        $this->line("- Productos actualizados:  {$result['total_updated']}");
        $this->line("- Productos sin cambios:   {$result['total_unchanged']}");
        $this->line("- Productos sin match SKU: {$result['total_skipped']}");

        if (!empty($result['errors'])) {
            $this->warn('Ocurrieron errores durante la sincronización:');
            foreach ($result['errors'] as $error) {
                $this->error("{$error['client']}: {$error['error']}");
            }
        }

        return Command::SUCCESS;
    }
}
