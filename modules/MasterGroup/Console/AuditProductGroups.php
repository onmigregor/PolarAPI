<?php
declare(strict_types=1);

namespace Modules\MasterGroup\Console;

use Illuminate\Console\Command;
use Modules\MasterGroup\Actions\AuditProductGroupsAction;

class AuditProductGroups extends Command
{
    protected $signature = 'analytics:audit-groups';
    protected $description = 'Audit products with missing groups across all tenants';

    public function handle(AuditProductGroupsAction $action): int
    {
        $this->info('Starting product group audit...');
        $result = $action->execute();

        $this->info("Audit completed. Total orphan products found: {$result['total_orphans']}");
        $this->info("Logs generated in: storage/logs/productos-sin-grupo-YYYY-MM-DD.log");

        return 0;
    }
}
