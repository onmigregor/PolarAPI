<?php

namespace Modules\Report\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\Report\Actions\ExportSalesCsvAction;
use Modules\Report\Actions\ExportObsequiosCsvAction;
use Modules\Report\Http\Requests\ExportSalesCsvRequest;
use Modules\Report\DataTransferObjects\ExportSalesCsvFilterData;

class ReportController extends Controller
{
    /**
     * Exportar ventas en formato CSV separado por ;
     */
    public function exportSalesCsv(
        ExportSalesCsvRequest $request,
        ExportSalesCsvAction $salesAction,
        ExportObsequiosCsvAction $obsqAction
    ): \Illuminate\Http\JsonResponse {
        $filters = ExportSalesCsvFilterData::fromRequest($request->validated());

        // Ejecutar acciones por separado
        $ventasRows = $salesAction->execute($filters);
        $obsqRows = $obsqAction->execute($filters);

        // Auditoría/Diagnóstico en el Log del HUB (Nivel Error para forzar visibilidad)
        \Illuminate\Support\Facades\Log::error("AUDITORIA REPORTE - Filtros: " . json_encode($filters));
        \Illuminate\Support\Facades\Log::error(" - Ventas encontradas: " . count($ventasRows));
        \Illuminate\Support\Facades\Log::error(" - Obsequios encontrados: " . count($obsqRows));

        // LOG DE ERRORES DE TENANTS (Si los hay)
        if (isset($salesAction->errors) && count($salesAction->errors) > 0) {
            foreach ($salesAction->errors as $error) {
                \Illuminate\Support\Facades\Log::error(" !!! ERROR EN TENANT [{$error['client']}]: {$error['error']}");
            }
        }

        // Cabeceras del CSV
        $headers = [
            'FQ/REDI',
            'CEP',
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


        $dateLabel = $filters->start_date;
        if (!empty($filters->start_date) && !empty($filters->end_date)) {
            $dateLabel = "{$filters->start_date}_to_{$filters->end_date}";
        }

        $ventasFilename = "ventas_{$dateLabel}.csv";
        $obsqFilename = "obsequios_{$dateLabel}.csv";

        $ventasCsv = $this->generateCsvContent($headers, $ventasRows);
        $obsqCsv = $this->generateCsvContent($headers, $obsqRows);

        $ventasZipFilename = str_replace('.csv', '.zip', $ventasFilename);
        $obsqZipFilename = str_replace('.csv', '.zip', $obsqFilename);

        try {
            if (config('app.env') === 'local') {
                // Asegurar que el directorio existe
                if (!file_exists(storage_path('ftp'))) {
                    mkdir(storage_path('ftp'), 0777, true);
                }
                // Guardar localmente solo en LOCAL
                file_put_contents(storage_path("ftp/{$ventasFilename}"), $ventasCsv);
                file_put_contents(storage_path("ftp/{$obsqFilename}"), $obsqCsv);
            } else {
                // Generar ZIPs en memoria para PRODUCCIÓN
                $ventasZipContent = $this->createZipContent($ventasFilename, $ventasCsv);
                $obsqZipContent = $this->createZipContent($obsqFilename, $obsqCsv);

                // Subir al SFTP en sus respectivas carpetas
                \Illuminate\Support\Facades\Storage::disk('sftp_ventas')->put($ventasZipFilename, $ventasZipContent);
                \Illuminate\Support\Facades\Storage::disk('sftp_obsequios')->put($obsqZipFilename, $obsqZipContent);
            }

            return response()->json([
                'success' => true,
                'message' => config('app.env') === 'local'
                    ? "Reportes generados localmente en storage/ftp."
                    : "Reportes generados y subidos al SFTP correctamente.",
                'data' => [
                    'ventas_file' => config('app.env') === 'local' ? $ventasFilename : $ventasZipFilename,
                    'ventas_count' => count($ventasRows),
                    'obsq_file' => config('app.env') === 'local' ? $obsqFilename : $obsqZipFilename,
                    'obsq_count' => count($obsqRows),
                    'destination' => config('app.env') === 'local' ? 'Local Storage' : 'SFTP Polar (Zipped)',
                ]
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Error en reporte: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => "Error al procesar archivos o subir al SFTP: " . $e->getMessage(),
            ], 500);
        }
    }

    private function generateCsvContent(array $headers, array $rows): string
    {
        $csvContent = implode(';', $headers) . "\r\n";
        foreach ($rows as $row) {
            $csvContent .= implode(';', [
                $row['fq_redi'],
                $row['cep'],
                $row['fecha'],
                $row['deudor'],
                $row['doc_fq_redi'],
                $row['material'],
                $row['cantidad'],
                $row['um'],
                $row['rif_ci_clte'],
                $row['cl_doc'],
                $row['motivo'],
            ]) . "\r\n";
        }
        return $csvContent;
    }

    /**
     * Crea un archivo ZIP en memoria y retorna su contenido binario.
     */
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
