<?php
declare(strict_types=1);

namespace Modules\MasterClient\Console;

use Illuminate\Console\Command;
use Modules\MasterClient\Actions\SyncMasterClientsAction;

use Illuminate\Support\Facades\Log;

class SyncMasterClients extends Command
{
    protected $signature = 'master-client:sync';
    protected $description = 'Sync clients from tenant databases to master_client_polar table';

    public function handle(SyncMasterClientsAction $action): int
    {
        $this->info('Starting client synchronization...');
        
        try {
            $result = $action->execute();

            $this->table(['Metric', 'Value'], [
                ['Synced Count', $result['synced_count']],
                ['Clients Processed', $result['clients_processed']],
                ['Errors', count($result['errors'])],
            ]);

            if (!empty($result['errors'])) {
                foreach ($result['errors'] as $err) {
                    $errMsg = "{$err['company_route']}: {$err['error']}";
                    $this->error($errMsg);
                    Log::channel('jobs_errors')->error("[master-client:sync] " . $errMsg);
                }
            }

            return 0;
        } catch (\Throwable $e) {
            $errMsg = "Fatal error in client sync: " . $e->getMessage();
            $this->error($errMsg);
            Log::channel('jobs_errors')->error("[master-client:sync] " . $errMsg . "\n" . $e->getTraceAsString());
            return 1;
        }
    }
}
