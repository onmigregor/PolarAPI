<?php
declare(strict_types=1);

namespace Modules\Report\Console;

use Illuminate\Console\Command;
use Modules\Report\Actions\ExportEpRequestsCsvAction;
use Illuminate\Support\Facades\Log;

class ExportEpRequestsCommand extends Command
{
    protected $signature = 'report:generate-ep-requests {--start_date= : Start date of the range (YYYY-MM-DD)} {--end_date= : End date of the range (YYYY-MM-DD)}';
    protected $description = 'Consolidates EP requests (Creacion, Cambio Estatus, Data Maestra) from active tenants, formats as CSV, and uploads to SFTP or local storage';

    public function handle(ExportEpRequestsCsvAction $action): int
    {
        $this->info("Starting EP requests report generation...");

        $startDate = $this->option('start_date');
        $endDate = $this->option('end_date');

        // Si no se proveen opciones, el Action usará la fecha de ayer (yesterday) por defecto.
        $this->info("Date range: " . ($startDate ?? 'Yesterday') . " to " . ($endDate ?? 'Yesterday'));

        try {
            $result = $action->executeAndUpload($startDate, $endDate);

            $this->info("Successfully generated EP requests reports!");
            $this->info("Destination: {$result['destination']}");
            foreach ($result['files'] as $f) {
                $this->info("- Table: {$f['table']} | File: {$f['file']} | Count: {$f['count']}");
            }

            return 0;
        } catch (\Throwable $e) {
            $errMsg = "Fatal error generating EP requests reports: " . $e->getMessage();
            $this->error($errMsg);
            Log::channel('jobs_errors')->error("[report:generate-ep-requests] " . $errMsg . "\n" . $e->getTraceAsString());
            return 1;
        }
    }
}
