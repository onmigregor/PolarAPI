<?php
declare(strict_types=1);

namespace Modules\MasterGroup\Console;

use Illuminate\Console\Command;
use Modules\MasterGroup\Actions\SyncMasterGroupsAction;

class SyncMasterGroups extends Command
{
    protected $signature = 'analytics:sync-groups';
    protected $description = 'Sync product groups from tenant databases to master table';

    public function handle(SyncMasterGroupsAction $action): int
    {
        $this->info('Starting group synchronization...');
        $result = $action->execute();

        $this->table(['Metric', 'Value'], [
            ['Synced Count', $result['synced_count']],
            ['Clients Processed', $result['clients_processed']],
            ['Errors', count($result['errors'])],
        ]);

        return 0;
    }
}
