<?php
declare(strict_types=1);

namespace Modules\Report\Console;

use Illuminate\Console\Command;
use Modules\Report\Actions\ExportCustomerConsolidatedAction;
use Illuminate\Support\Facades\Log;

class ExportCustomerConsolidatedCommand extends \App\Console\Commands\BaseReportCommand
{
    protected $signature = 'report:generate-customer-consolidated';
    protected $description = 'Consolidates all unlinked clients from active tenants, formats as CSV/ZIP, and uploads to SFTP or local storage';

    protected function getProcessName(): string
    {
        return 'customer_consolidated';
    }

    public function handle(ExportCustomerConsolidatedAction $action): int
    {
        $this->info("Starting customer consolidated report generation...");

        try {
            $table = $this->getRoutesTable();
            $disk = $this->getSftpDisk('sftp_obsequios');
            $result = $action->executeAndUpload($table, $disk);

            $this->info("Successfully generated customer consolidated report!");
            $this->info("Destination: {$result['destination']}");
            $this->info("Filename: {$result['filename']}");
            $this->info("Total Customers: {$result['count']}");

            return 0;
        } catch (\Throwable $e) {
            $errMsg = "Fatal error generating customer consolidated report: " . $e->getMessage();
            $this->error($errMsg);
            Log::channel('jobs_errors')->error("[report:generate-customer-consolidated] " . $errMsg . "\n" . $e->getTraceAsString());
            return 1;
        }
    }
}
