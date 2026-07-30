<?php

namespace Modules\Report\Actions;

use ZipArchive;
use Illuminate\Support\Facades\Log;

class ExportFilteredReportAction
{
    public function __construct(
        private GetSalesObsequiosReportAction $reportAction
    ) {}

    /**
     * Genera el archivo ZIP temporal conteniendo ventas y obsequios filtrados.
     *
     * @param array $filters Filtros del reporte
     * @param string $format 'txt' | 'csv'
     * @return string Ruta del archivo ZIP generado
     */
    public function execute(array $filters, string $format = 'csv'): string
    {
        // 1. Obtener la data filtrada completa
        $data = $this->reportAction->execute($filters);
        $ventas = $data['ventas']['data'] ?? [];
        $obsequios = $data['obsequios']['data'] ?? [];

        // Determinar delimitador y extensión
        $delimiter = $format === 'txt' ? ',' : ';';
        $extension = $format === 'txt' ? 'txt' : 'csv';

        // 2. Formatear Ventas
        $ventasHeaders = [
            'Distribuidora',
            'ID Venta',
            'Fecha Venta',
            'ID Cliente',
            'Cliente',
            'RIF',
            'Monto Total',
            'Status Envio',
            'Fecha Envio SFTP'
        ];
        $ventasLines = [];
        $ventasLines[] = $this->toCsvRow($ventasHeaders, $delimiter);
        foreach ($ventas as $v) {
            $ventasLines[] = $this->toCsvRow([
                $v['tenant_name'] ?? '',
                $v['id_venta'] ?? '',
                $v['fecha_venta'] ?? '',
                $v['id_cliente'] ?? '',
                $v['nombre_cliente'] ?? '',
                $v['rif'] ?? '',
                $v['total_venta'] ?? '',
                $v['status_envio'] ?? '',
                $v['fecha_envio_sftp'] ?? ''
            ], $delimiter);
        }
        $ventasContent = implode("\r\n", $ventasLines);

        // 3. Formatear Obsequios
        $obsequiosHeaders = [
            'Distribuidora',
            'ID Obsequio',
            'ID Venta',
            'Fecha Entrega',
            'Cantidad',
            'SKU',
            'Producto',
            'ID Cliente',
            'Cliente',
            'RIF',
            'Status Envio',
            'Fecha Envio SFTP'
        ];
        $obsequiosLines = [];
        $obsequiosLines[] = $this->toCsvRow($obsequiosHeaders, $delimiter);
        foreach ($obsequios as $o) {
            $obsequiosLines[] = $this->toCsvRow([
                $o['tenant_name'] ?? '',
                $o['obsq_id'] ?? '',
                $o['id_venta'] ?? '',
                $o['fecha_entrega'] ?? '',
                $o['cantidad'] ?? '',
                $o['codigo_sku'] ?? '',
                $o['nombre_producto'] ?? '',
                $o['id_cliente'] ?? '',
                $o['nombre_cliente'] ?? '',
                $o['rif'] ?? '',
                $o['status_envio'] ?? '',
                $o['fecha_envio_sftp'] ?? ''
            ], $delimiter);
        }
        $obsequiosContent = implode("\r\n", $obsequiosLines);

        // 4. Crear el archivo ZIP temporal
        $tempZipPath = tempnam(sys_get_temp_dir(), 'report_zip_');
        // Renombrar con extensión .zip
        unlink($tempZipPath);
        $tempZipPath .= '.zip';

        $zip = new ZipArchive();
        if ($zip->open($tempZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            $zip->addFromString("ventas.{$extension}", $ventasContent);
            $zip->addFromString("obsequios.{$extension}", $obsequiosContent);
            $zip->close();
            
            return $tempZipPath;
        }

        throw new \Exception("No se pudo crear el archivo ZIP para exportar el reporte.");
    }

    /**
     * Formatea un arreglo de campos a una fila tipo CSV con delimitador especificado.
     */
    private function toCsvRow(array $fields, string $delimiter): string
    {
        $escaped = array_map(function ($field) {
            $fieldStr = (string)$field;
            // Reemplazar saltos de línea e incorporar comillas si contiene comillas o el delimitador
            $fieldStr = str_replace(["\r", "\n"], [" ", " "], $fieldStr);
            if (str_contains($fieldStr, '"') || str_contains($fieldStr, ',') || str_contains($fieldStr, ';')) {
                $fieldStr = '"' . str_replace('"', '""', $fieldStr) . '"';
            }
            return $fieldStr;
        }, $fields);

        return implode($delimiter, $escaped);
    }
}
