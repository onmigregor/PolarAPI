<?php
declare(strict_types=1);

namespace Modules\Report\Console;

use Illuminate\Console\Command;
use Modules\Report\Actions\GenerateDailySalesReportsAction;
use Modules\Report\DataTransferObjects\ExportSalesCsvFilterData;

use Illuminate\Support\Facades\Log;

class GenerateDailySalesReportsCommand extends \App\Console\Commands\BaseReportCommand
{
    protected $signature = 'report:generate-daily-sales {--date= : The date for which to generate the report (YYYY-MM-DD), defaults to yesterday}';
    protected $description = 'Generate daily sales and obsequios reports for the specified date (defaults to yesterday) and upload to SFTP';

    protected function getProcessName(): string
    {
        return 'sales';
    }

    public function handle(GenerateDailySalesReportsAction $action): int
    {
        $date = $this->option('date');

        if (!$date) {
            $date = now()->subDay()->format('Y-m-d');
        }

        $this->info("Starting daily report generation for date: {$date}...");

        $filters = new ExportSalesCsvFilterData(
            start_date: $date,
            end_date: null,
            route_code: null
        );

        $ventasDisk = $this->getSftpDisk('sftp_ventas');
        $obsqDisk = \App\Helpers\ReportRoutesSelector::getSftpDiskForProcess('obsequios', 'sftp_obsequios');
        $obsqSapDisk = \App\Helpers\ReportRoutesSelector::getSftpDiskForProcess('obsequios_sap', 'sftp_obsequios');

        $ventasTable = $this->getRoutesTable();
        $obsqTable = \App\Helpers\ReportRoutesSelector::getTableForProcess('obsequios');
        $obsqSapTable = \App\Helpers\ReportRoutesSelector::getTableForProcess('obsequios_sap');

        try {
            $result = $action->execute(
                $filters,
                $ventasDisk,
                $obsqDisk,
                $obsqSapDisk,
                $ventasTable,
                $obsqTable,
                $obsqSapTable
            );

            $this->info("Successfully generated daily reports!");
            $this->info("Destination: {$result['destination']}");
            $this->info("Total Ventas: {$result['total_ventas']}");
            $this->info("Total Obsequios: {$result['total_obsq']}");
            $this->info("Total Obsequios SAP: {$result['total_obsq_sap']}");

            if (!empty($result['errors'])) {
                $this->warn("The following errors occurred during execution:");
                foreach ($result['errors'] as $err) {
                    $errMsg = "Client {$err['client']}: {$err['error']}";
                    $this->error($errMsg);
                    Log::channel('jobs_errors')->error("[report:generate-daily-sales] " . $errMsg);
                }
            }

            return 0;
        } catch (\Throwable $e) {
            $errMsg = "Fatal error generating daily reports: " . $e->getMessage();
            $this->error($errMsg);
            Log::channel('jobs_errors')->error("[report:generate-daily-sales] " . $errMsg . "\n" . $e->getTraceAsString());
            return 1;
        }
    }
}
