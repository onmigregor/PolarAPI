<?php

namespace Modules\Analytics\Console;

use Illuminate\Console\Command;
use Modules\Analytics\Actions\SyncMasterProductsAction;

class SyncMasterProducts extends Command
{
    protected $signature = 'products:sync';
    protected $description = 'Sync products from all client databases to master catalog';

    public function handle(SyncMasterProductsAction $action): int
    {
        $this->info('Starting product synchronization...');

        $result = $action->execute();

        $this->info("Synced {$result['synced_count']} products from {$result['clients_processed']} clients.");

        if (!empty($result['errors'])) {
            $this->warn('Errors occurred during sync:');
            foreach ($result['errors'] as $error) {
                $this->error("{$error['client']}: {$error['error']}");
            }
        }

        return Command::SUCCESS;
    }
}
