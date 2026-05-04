<?php

namespace Modules\MasterPromotion\Console;

use Illuminate\Console\Command;
use Modules\MasterPromotion\Actions\SyncPromotionsToClientsAction;

class SyncPromotionsToClients extends Command
{
    protected $signature = 'promotions:push-to-clients';

    protected $description = 'Distribuye las promociones del HUB maestro hacia todos los Tenants activos';

    public function handle(SyncPromotionsToClientsAction $action): int
    {
        $this->info('Iniciando distribución de promociones HUB → Tenants...');

        $result = $action->execute();

        $this->newLine();
        $this->table(['Métrica', 'Valor'], [
            ['Tenants procesados', $result['tenants_processed']],
            ['Tenants sin promociones', $result['tenants_skipped']],
            ['Promociones sincronizadas', $result['promotions_synced']],
            ['Productos sincronizados', $result['products_synced']],
            ['Promociones previas eliminadas', $result['promotions_deleted']],
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
