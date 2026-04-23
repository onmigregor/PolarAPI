<?php
declare(strict_types=1);

namespace Modules\MasterClient\Console;

use Illuminate\Console\Command;
use Modules\MasterClient\Actions\SyncOfficialCustomersAction;

class SyncOfficialCustomers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'customers:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync official customer data from Polar Master to PolarAPI and Tenant databases';

    /**
     * Execute the console command.
     */
    public function handle(SyncOfficialCustomersAction $action): int
    {
        $this->info('Starting official customer synchronization...');
        $this->info('This will create new tenant databases if they do not exist.');

        $result = $action->execute();

        $this->table(['Metric', 'Count'], [
            ['Tenants Processed (Infrastructure)', $result['tenants_processed']],
            ['Customers Synced (Master)', $result['customers_synced_master']],
            ['Customers Pushed (Tenants)', $result['customers_pushed_tenants']],
        ]);

        if (!empty($result['errors'])) {
            $this->warn('Errors encountered during sync:');
            foreach ($result['errors'] as $error) {
                $this->error($error);
            }
        }

        $this->info('Sync process completed.');
        return 0;
    }
}
