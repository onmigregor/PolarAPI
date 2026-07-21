<?php

namespace Modules\Report\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\Report\Actions\GenerateDailySalesReportsAction;
use Modules\Report\Actions\ExportAdcConsolidatedAction;
use Modules\Report\Actions\ExportCustomerConsolidatedAction;
use Modules\Report\Http\Requests\ExportSalesCsvRequest;
use Modules\Report\DataTransferObjects\ExportSalesCsvFilterData;

class ReportController extends Controller
{
    /**
     * Exportar ventas en formato CSV separado por ;
     */
    public function exportSalesCsv(
        ExportSalesCsvRequest $request,
        GenerateDailySalesReportsAction $action
    ): \Illuminate\Http\JsonResponse {
        try {
            $filters = ExportSalesCsvFilterData::fromRequest($request->validated());

            $ventasDisk = \App\Helpers\ReportRoutesSelector::getSftpDiskForProcess('sales', 'sftp_ventas');
            $obsqDisk = \App\Helpers\ReportRoutesSelector::getSftpDiskForProcess('obsequios', 'sftp_obsequios');
            $obsqSapDisk = \App\Helpers\ReportRoutesSelector::getSftpDiskForProcess('obsequios_sap', 'sftp_obsequios');

            $ventasTable = \App\Helpers\ReportRoutesSelector::getTableForProcess('sales');
            $obsqTable = \App\Helpers\ReportRoutesSelector::getTableForProcess('obsequios');
            $obsqSapTable = \App\Helpers\ReportRoutesSelector::getTableForProcess('obsequios_sap');

            $result = $action->execute($filters, $ventasDisk, $obsqDisk, $obsqSapDisk, $ventasTable, $obsqTable, $obsqSapTable);

            return response()->json([
                'success' => true,
                'message' => config('app.env') === 'local'
                    ? "Reportes diarios generados localmente en storage/ftp."
                    : "Reportes diarios generados y subidos al SFTP correctamente.",
                'data' => $result
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Error en reporte: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => "Error al procesar archivos o subir al SFTP: " . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Exportar reporte consolidado de ADC
     */
    public function exportAdcConsolidated(ExportAdcConsolidatedAction $action): \Illuminate\Http\JsonResponse
    {
        try {
            $routesTable = \App\Helpers\ReportRoutesSelector::getTableForProcess('adc');
            $sftpDisk = \App\Helpers\ReportRoutesSelector::getSftpDiskForProcess('adc', 'sftp_obsequios');

            $rows = $action->execute($routesTable);
            
            $headers = [
                'FQ/REDI',
                'Idcustomer',
                'MARCA',
                'No SERIE',
                'No ACTIVO',
                'Empresa',
                'ESTADO',
                'Tipo de activo',
                'condicion',
                'FECHA ACT.'
            ];

            $filename = "ADC_DATOS_" . now()->format('Ymd_His') . ".txt";
            
            $csvContent = implode(';', $headers) . "\r\n";
            foreach ($rows as $row) {
                $csvContent .= implode(';', [
                    $row['fq_redi'],
                    $row['id_customer'],
                    $row['marca'],
                    $row['no_serie'],
                    $row['no_activo'],
                    $row['empresa'],
                    $row['estado'],
                    $row['tipo_activo'],
                    $row['condicion_val'], // 1 o 0
                    $row['updated_at'] ?? '',
                ]) . "\r\n";
            }

            if (config('app.env') === 'local') {
                if (!file_exists(storage_path('ftp'))) {
                    mkdir(storage_path('ftp'), 0777, true);
                }
                file_put_contents(storage_path("ftp/{$filename}"), $csvContent);
            } else {
                $zipFilename = str_replace('.txt', '.zip', $filename);
                $zipContent = $this->createZipContent($filename, $csvContent);
                // Usar el disco SFTP resuelto dinámicamente según .env
                \Illuminate\Support\Facades\Storage::disk($sftpDisk)->put($zipFilename, $zipContent);
            }

            return response()->json([
                'success' => true,
                'message' => config('app.env') === 'local'
                    ? "Reporte ADC generado localmente."
                    : "Reporte ADC enviado al SFTP correctamente.",
                'data' => [
                    'filename' => $filename,
                    'count' => count($rows),
                    'destination' => config('app.env') === 'local' ? 'Local Storage' : 'SFTP'
                ]
            ]);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Error en reporte ADC: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => "Error al procesar reporte ADC: " . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Exportar reporte consolidado de Clientes de todos los tenants
     */
    public function exportCustomerConsolidated(ExportCustomerConsolidatedAction $action): \Illuminate\Http\JsonResponse
    {
        try {
            $table = \App\Helpers\ReportRoutesSelector::getTableForProcess('customer_consolidated');
            $disk = \App\Helpers\ReportRoutesSelector::getSftpDiskForProcess('customer_consolidated', 'sftp_obsequios');
            $result = $action->executeAndUpload($table, $disk);

            return response()->json([
                'success' => true,
                'message' => config('app.env') === 'local'
                    ? "Reporte de Clientes generado localmente."
                    : "Reporte de Clientes enviado al SFTP correctamente.",
                'data' => [
                    'filename' => $result['filename'],
                    'count' => $result['count'],
                    'destination' => $result['destination']
                ]
            ]);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Error en reporte Clientes: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => "Error al procesar reporte de Clientes: " . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Exportar solicitudes EP (Nuevos, Actualizacion, Estatus) a CSV y SFTP
     */
    public function exportEpRequestsCsv(\Illuminate\Http\Request $request, \Modules\Report\Actions\ExportEpRequestsCsvAction $action): \Illuminate\Http\JsonResponse
    {
        try {
            $validated = $request->validate([
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
            ]);

            $routesTable = \App\Helpers\ReportRoutesSelector::getTableForProcess('clientes');
            $sftpDisk = \App\Helpers\ReportRoutesSelector::getSftpDiskForProcess('clientes', 'sftp_solicitudes');

            $result = $action->executeAndUpload($validated['start_date'], $validated['end_date'], $routesTable, $sftpDisk);

            return response()->json([
                'success' => true,
                'message' => config('app.env') === 'local'
                    ? "Reportes EP generados localmente en storage/ftp."
                    : "Reportes EP generados y subidos al SFTP correctamente.",
                'data' => [
                    'files' => $result['files'],
                    'destination' => $result['destination'],
                ]
            ]);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Error en reporte EP: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => "Error al procesar reporte EP: " . $e->getMessage(),
            ], 500);
        }
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
