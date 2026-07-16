<?php

namespace Modules\Report\Actions;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Modules\Report\DataTransferObjects\ExportSalesCsvFilterData;

class GenerateDailySalesReportsAction
{
    public function __construct(
        private ExportSalesCsvAction $salesAction,
        private ExportObsequiosCsvAction $obsqAction,
        private ExportObsequiosSapAction $obsqSapAction
    ) {}

    public function execute(
        ExportSalesCsvFilterData $filters,
        string $ventasDisk = 'sftp_ventas',
        string $obsqDisk = 'sftp_obsequios',
        string $obsqSapDisk = 'sftp_obsequios',
        string $ventasTable = 'company_routes',
        string $obsqTable = 'company_routes',
        string $obsqSapTable = 'company_routes'
    ): array
    {
        // 1. Ejecutar acciones por separado
        $ventasRows = $this->salesAction->execute($filters, $ventasTable);
        $obsqRows = $this->obsqAction->execute($filters, $obsqTable);
        $obsqSapRows = $this->obsqSapAction->execute($filters, $obsqSapTable);

        // 2. Agrupar filas por fecha (formato d.m.Y)
        $ventasByDate = [];
        foreach ($ventasRows as $row) {
            $ventasByDate[$row['fecha']][] = $row;
        }
        
        $obsqByDate = [];
        foreach ($obsqRows as $row) {
            $obsqByDate[$row['fecha']][] = $row;
        }
        
        $obsqSapByDate = [];
        foreach ($obsqSapRows as $row) {
            $fechaSap = $row[5] ?? null;
            if ($fechaSap) {
                $obsqSapByDate[$fechaSap][] = $row;
            }
        }

        // Auditoría/Diagnóstico en el Log del HUB
        Log::error("AUDITORIA REPORTE PROGRAMADO - Filtros: " . json_encode($filters));
        Log::error(" - Ventas totales: " . count($ventasRows));
        Log::error(" - Obsequios totales: " . count($obsqRows));
        Log::error(" - Obsequios SAP totales: " . count($obsqSapRows));

        if (isset($this->salesAction->errors) && count($this->salesAction->errors) > 0) {
            foreach ($this->salesAction->errors as $error) {
                Log::error(" !!! ERROR EN TENANT [{$error['client']}]: {$error['error']}");
            }
        }

        $headers = [
            'FQ/REDI',
            'Fecha Creacion',
            'Deudor',
            'Doc FQ/REDI',
            'material',
            'Cantidad',
            'UM',
            'RIF_CI_CLTE',
            'Cl. Doc',
            'Motivo',
        ];

        $obsqHeaders = array_merge($headers, ['Centro']);

        $isLocal = config('app.env') === 'local';
        $timeStr = now()->format('His');

        $startDate = Carbon::parse($filters->start_date);
        $endDate = $filters->end_date ? Carbon::parse($filters->end_date) : $startDate->copy();
        
        $generatedData = [];
        $currentDate = $startDate->copy();

        while ($currentDate->lte($endDate)) {
            $dateFormatted = $currentDate->format('d.m.Y');
            $dateSuffix = $currentDate->format('Ymd') . '_' . $timeStr;

            $ventasForDay = $ventasByDate[$dateFormatted] ?? [];
            $obsqForDay = $obsqByDate[$dateFormatted] ?? [];
            $obsqSapForDay = $obsqSapByDate[$dateFormatted] ?? [];

            $ventasFilename = "VENTA_{$dateSuffix}.txt";
            $obsqFilename = "OBSEQUIO_{$dateSuffix}.txt";
            $obsqSapFilename = "OBSEQUIO_SAP_{$dateSuffix}.csv";

            $ventasCsv = $this->generateCsvContent($headers, $ventasForDay);
            $obsqCsv = $this->generateCsvContent($obsqHeaders, $obsqForDay);
            $obsqSapCsv = $this->obsqSapAction->generateCsvContent($obsqSapForDay);

            $ventasZipFilename = "VENTA_{$dateSuffix}.ZIP";
            $obsqZipFilename = "OBSEQUIO_{$dateSuffix}.ZIP";
            $obsqSapZipFilename = "OBSEQUIO_SAP_{$dateSuffix}.ZIP";

            if ($isLocal) {
                if (!file_exists(storage_path('ftp'))) {
                    mkdir(storage_path('ftp'), 0777, true);
                }
                file_put_contents(storage_path("ftp/{$ventasFilename}"), $ventasCsv);
                file_put_contents(storage_path("ftp/{$obsqFilename}"), $obsqCsv);
                file_put_contents(storage_path("ftp/{$obsqSapFilename}"), $obsqSapCsv);
            } else {
                $ventasZipContent = $this->createZipContent($ventasFilename, $ventasCsv);
                $obsqZipContent = $this->createZipContent($obsqFilename, $obsqCsv);
                $obsqSapZipContent = $this->createZipContent($obsqSapFilename, $obsqSapCsv);
                
                Storage::disk($ventasDisk)->put($ventasZipFilename, $ventasZipContent);
                Storage::disk($obsqDisk)->put($obsqZipFilename, $obsqZipContent);
                Storage::disk($obsqSapDisk)->put($obsqSapZipFilename, $obsqSapZipContent);
            }

            $generatedData[] = [
                'date' => $dateFormatted,
                'ventas_file' => $isLocal ? $ventasFilename : $ventasZipFilename,
                'ventas_count' => count($ventasForDay),
                'obsq_file' => $isLocal ? $obsqFilename : $obsqZipFilename,
                'obsq_count' => count($obsqForDay),
                'obsq_sap_file' => $isLocal ? $obsqSapFilename : $obsqSapZipFilename,
                'obsq_sap_count' => count($obsqSapForDay),
            ];

            $currentDate->addDay();
        }

        return [
            'files' => $generatedData,
            'total_ventas' => count($ventasRows),
            'total_obsq' => count($obsqRows),
            'total_obsq_sap' => count($obsqSapRows),
            'destination' => $isLocal ? 'Local Storage' : 'SFTP Polar (Zipped)',
            'errors' => $this->salesAction->errors ?? [],
        ];
    }

    private function generateCsvContent(array $headers, array $rows): string
    {
        $csvContent = implode(';', $headers) . "\r\n";
        foreach ($rows as $row) {
            $fields = [
                $row['fq_redi'],
                $row['fecha'],
                $row['cep'],
                $row['doc_fq_redi'],
                $row['material'],
                $row['cantidad'],
                $row['um'],
                $row['rif_ci_clte'],
                $row['cl_doc'],
                $row['motivo'],
            ];
            if (isset($row['centro'])) {
                $fields[] = $row['centro'];
            }
            $csvContent .= implode(';', $fields) . "\r\n";
        }
        return $csvContent;
    }

    private function createZipContent(string $filenameInZip, string $content): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'zip');
        $zip = new \ZipArchive();
        
        if ($zip->open($tempFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
            $zip->addFromString($filenameInZip, $content);
            $zip->close();
            
            $zipContent = file_get_contents($tempFile);
            unlink($tempFile);
            return $zipContent;
        }

        throw new \Exception("No se pudo crear el archivo ZIP temporal.");
    }
}
