<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Report\Actions\ExportSalesWithGiftsBatchAction;
use Modules\Report\DataTransferObjects\ExportSalesCsvFilterData;

class ExportSalesWithGiftsCommand extends Command
{
    /**
     * El nombre y firma del comando de consola.
     */
    protected $signature = 'report:export-sales-with-gifts 
                            {--start-date= : Fecha de inicio YYYY-MM-DD} 
                            {--end-date= : Fecha de fin YYYY-MM-DD}
                            {--table=company_routes : Tabla de rutas a consultar}';

    /**
     * La descripción del comando.
     */
    protected $description = 'Exporta en un archivo ZIP especial diferenciado únicamente las ventas que poseen obsequios asociados en el período.';

    public function handle(ExportSalesWithGiftsBatchAction $action): int
    {
        $startDate = $this->option('start-date') ?? now()->startOfMonth()->format('Y-m-d');
        $endDate = $this->option('end-date') ?? now()->format('Y-m-d');
        $table = $this->option('table') ?? 'company_routes';

        $this->info("🔎 Extrayendo ventas con obsequios entre {$startDate} y {$endDate}...");

        $filters = new ExportSalesCsvFilterData(
            start_date: $startDate,
            end_date: $endDate,
            route_code: null
        );

        $rows = $action->execute($filters, $table);
        $totalRows = count($rows);

        if ($totalRows === 0) {
            $this->warn("⚠️ No se encontraron ventas con obsequios en el período indicado.");
            return self::SUCCESS;
        }

        $this->info("✅ Se encontraron {$totalRows} filas de ventas con obsequio. Generando archivo ZIP...");

        $zipFilename = $action->generateZipReport($rows);

        $this->info("🎉 Archivo generado exitosamente: {$zipFilename}");
        return self::SUCCESS;
    }
}
