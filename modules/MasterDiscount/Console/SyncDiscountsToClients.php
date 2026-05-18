<?php

namespace Modules\MasterDiscount\Console;

use Illuminate\Console\Command;
use Modules\MasterDiscount\Actions\SyncDiscountsToClientsAction;

class SyncDiscountsToClients extends Command
{
    protected $signature = 'discounts:push-to-clients';

    protected $description = 'Distribuye los descuentos del HUB maestro hacia todos los Tenants activos';

    public function handle(SyncDiscountsToClientsAction $action): int
    {
        $this->info('Iniciando distribución de descuentos HUB → Tenants...');

        $result = $action->execute();

        $this->newLine();
        $this->table(['Métrica', 'Valor'], [
            ['Tenants procesados', $result['tenants_processed']],
            ['Tenants sin descuentos', $result['tenants_skipped']],
            ['Descuentos sincronizados', $result['discounts_synced']],
            ['Productos sincronizados', $result['products_synced']],
            ['Descuentos previos eliminados', $result['discounts_deleted']],
        ]);

        if (!empty($result['errors'])) {
            $this->newLine();
            $this->warn('Errores durante la sincronización:');
            foreach ($result['errors'] as $error) {
                $this->error("  [{$error['tenant']}] ({$error['db']}): {$error['error']}");
            }
        }

        $this->newLine();
        $this->info('Distribución completada.');
        return 0;
    }
}
