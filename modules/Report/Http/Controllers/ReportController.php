<?php

namespace Modules\Report\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\Report\Actions\ExportSalesCsvAction;
use Modules\Report\Actions\ExportObsequiosCsvAction;
use Modules\Report\Actions\ExportObsequiosSapAction;
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
        ExportSalesCsvAction $salesAction,
        ExportObsequiosCsvAction $obsqAction,
        ExportObsequiosSapAction $obsqSapAction
    ): \Illuminate\Http\JsonResponse {
        $filters = ExportSalesCsvFilterData::fromRequest($request->validated());

        // Ejecutar acciones por separado
        $ventasRows = $salesAction->execute($filters);
        $obsqRows = $obsqAction->execute($filters);
        $obsqSapRows = $obsqSapAction->execute($filters);

        // Auditoría/Diagnóstico en el Log del HUB (Nivel Error para forzar visibilidad)
        \Illuminate\Support\Facades\Log::error("AUDITORIA REPORTE - Filtros: " . json_encode($filters));
        \Illuminate\Support\Facades\Log::error(" - Ventas encontradas: " . count($ventasRows));
        \Illuminate\Support\Facades\Log::error(" - Obsequios encontrados: " . count($obsqRows));
        \Illuminate\Support\Facades\Log::error(" - Obsequios SAP encontrados: " . count($obsqSapRows));

        // LOG DE ERRORES DE TENANTS (Si los hay)
        if (isset($salesAction->errors) && count($salesAction->errors) > 0) {
            foreach ($salesAction->errors as $error) {
                \Illuminate\Support\Facades\Log::error(" !!! ERROR EN TENANT [{$error['client']}]: {$error['error']}");
            }
        }

        // Cabeceras del CSV
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


        $dateLabel = $filters->start_date;
        if (!empty($filters->start_date) && !empty($filters->end_date)) {
            $dateLabel = "{$filters->start_date}_to_{$filters->end_date}";
        }

        $ventasFilename = "ventas_{$dateLabel}.txt";
        $obsqFilename = "obsequios_{$dateLabel}.txt";
        $obsqSapFilename = "obsequios_sap_{$dateLabel}.csv";

        $ventasCsv = $this->generateCsvContent($headers, $ventasRows);
        $obsqCsv = $this->generateCsvContent($headers, $obsqRows);
        $obsqSapCsv = $obsqSapAction->generateCsvContent($obsqSapRows);

        $ventasZipFilename = str_replace('.txt', '.zip', $ventasFilename);
        $obsqZipFilename = str_replace('.txt', '.zip', $obsqFilename);
        $obsqSapZipFilename = str_replace('.csv', '.zip', $obsqSapFilename);

        try {
            if (config('app.env') === 'local') {
                // Asegurar que el directorio existe
                if (!file_exists(storage_path('ftp'))) {
                    mkdir(storage_path('ftp'), 0777, true);
                }
                // Guardar localmente solo en LOCAL
                file_put_contents(storage_path("ftp/{$ventasFilename}"), $ventasCsv);
                file_put_contents(storage_path("ftp/{$obsqFilename}"), $obsqCsv);
                file_put_contents(storage_path("ftp/{$obsqSapFilename}"), $obsqSapCsv);
            } else {
                // Generar ZIPs en memoria para PRODUCCIÓN
                $ventasZipContent = $this->createZipContent($ventasFilename, $ventasCsv);
                $obsqZipContent = $this->createZipContent($obsqFilename, $obsqCsv);
                $obsqSapZipContent = $this->createZipContent($obsqSapFilename, $obsqSapCsv);

                // Subir al SFTP en la misma ruta unificada (sftp_ventas)
                \Illuminate\Support\Facades\Storage::disk('sftp_ventas')->put($ventasZipFilename, $ventasZipContent);
                \Illuminate\Support\Facades\Storage::disk('sftp_ventas')->put($obsqZipFilename, $obsqZipContent);
                \Illuminate\Support\Facades\Storage::disk('sftp_ventas')->put($obsqSapZipFilename, $obsqSapZipContent);
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
                    'obsq_sap_file' => config('app.env') === 'local' ? $obsqSapFilename : $obsqSapZipFilename,
                    'obsq_sap_count' => count($obsqSapRows),
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
                'Estado'
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

    private function generateCsvContent(array $headers, array $rows): string
    {
        $csvContent = implode(';', $headers) . "\r\n";
        foreach ($rows as $row) {
            $csvContent .= implode(';', [
                $row['fq_redi'],
                $row['fecha'],
                $row['cep'], // Ahora el valor de CEP va en la columna Deudor
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
