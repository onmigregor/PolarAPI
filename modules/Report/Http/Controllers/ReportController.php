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
            $result = $action->execute($filters);

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
            $rows = $action->execute();
            
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
                // Usar el disco de obsequios (que apunta a la carpeta /Manual)
                \Illuminate\Support\Facades\Storage::disk('sftp_obsequios')->put($zipFilename, $zipContent);
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
            $rows = $action->execute();
            
            $headers = [
                'FQ/REDI',
                'Codigo Cliente',
                'Nombre',
                'RIF',
                'Tipo Cliente',
                'Direccion',
                'Ruta',
                'Telefono',
                'Email',
                'Contacto',
                'Latitud',
                'Longitud',
                'Condicion Pago',
                'Lista Precios',
                'Sucursal',
                'Estado',
                'Motivo no CEP'
            ];

            $filename = "CLIENTES_CONSOLIDADO_" . now()->format('Ymd_His') . ".txt";
            
            $csvContent = implode(';', $headers) . "\r\n";
            foreach ($rows as $row) {
                $csvContent .= implode(';', [
                    $row['fq_redi'],
                    $row['codigo_cliente'],
                    $row['nombre'],
                    $row['rif'],
                    $row['tipo_cliente'],
                    $row['direccion'],
                    $row['ruta'],
                    $row['telefono'],
                    $row['email'],
                    $row['contacto'],
                    $row['latitud'],
                    $row['longitud'],
                    $row['condicion_pago'],
                    $row['lista_precios'],
                    $row['sucursal'],
                    $row['estado'],
                    $row['motivo_no_cep'],
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
                \Illuminate\Support\Facades\Storage::disk('sftp_obsequios')->put($zipFilename, $zipContent);
            }

            return response()->json([
                'success' => true,
                'message' => config('app.env') === 'local'
                    ? "Reporte de Clientes generado localmente."
                    : "Reporte de Clientes enviado al SFTP correctamente.",
                'data' => [
                    'filename' => $filename,
                    'count' => count($rows),
                    'destination' => config('app.env') === 'local' ? 'Local Storage' : 'SFTP'
                ]
            ]);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Error en reporte Clientes: " . $e->getMessage());
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

            $startDate = \Carbon\Carbon::parse($validated['start_date']);
            $endDate = \Carbon\Carbon::parse($validated['end_date']);

            $tables = [
                'clientes_nuevos_ep',
                'clientes_actualizacion_ep',
                'clientes_cambio_estatus_ep'
            ];

            $isLocal = config('app.env') === 'local';
            $timeStr = now()->format('His');
            $generatedData = [];

            foreach ($tables as $table) {
                // Obtener todos los datos del rango
                $allRows = $action->execute($table, $startDate->format('Y-m-d'), $endDate->format('Y-m-d'));
                
                $csvContent = $action->generateCsvContent($allRows);

                $filename = strtoupper($table) . "_{$timeStr}.csv";
                $zipFilename = strtoupper($table) . "_{$timeStr}.zip";

                if ($isLocal) {
                    if (!file_exists(storage_path('ftp'))) {
                        mkdir(storage_path('ftp'), 0777, true);
                    }
                    file_put_contents(storage_path("ftp/{$filename}"), $csvContent);
                } else {
                    $zipContent = $this->createZipContent($filename, $csvContent);
                    // Usar el disco de solicitudes (por defecto hereda la ruta de obsequios)
                    \Illuminate\Support\Facades\Storage::disk('sftp_solicitudes')->put($zipFilename, $zipContent);
                }

                $generatedData[] = [
                    'table' => $table,
                    'file' => $isLocal ? $filename : $zipFilename,
                    'count' => count($allRows),
                ];
            }

            return response()->json([
                'success' => true,
                'message' => $isLocal
                    ? "Reportes EP generados localmente en storage/ftp."
                    : "Reportes EP generados y subidos al SFTP correctamente.",
                'data' => [
                    'files' => $generatedData,
                    'destination' => $isLocal ? 'Local Storage' : 'SFTP Polar (/out/manual)',
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
