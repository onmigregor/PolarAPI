<?php
declare(strict_types=1);

namespace Modules\MasterClient\Console;

use Illuminate\Console\Command;
use Modules\MasterClient\Actions\SyncMasterClientsAction;

class SyncMasterClients extends Command
{
    protected $signature = 'master-client:sync';
    protected $description = 'Sync clients from tenant databases to master_clients table';

    public function handle(SyncMasterClientsAction $action): int
    {
        $this->info('Starting client synchronization...');
        $result = $action->execute();

        $this->table(['Metric', 'Value'], [
            ['Synced Count', $result['synced_count']],
            ['Clients Processed', $result['clients_processed']],
            ['Errors', count($result['errors'])],
        ]);

        if (!empty($result['errors'])) {
            foreach ($result['errors'] as $err) {
                $this->error("{$err['company_route']}: {$err['error']}");
            }
        }

        return 0;
    }
}
