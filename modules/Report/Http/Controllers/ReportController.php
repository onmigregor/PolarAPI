<?php

namespace Modules\Report\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\Report\Actions\ExportSalesCsvAction;
use Modules\Report\Http\Requests\ExportSalesCsvRequest;
use Modules\Report\DataTransferObjects\ExportSalesCsvFilterData;

class ReportController extends Controller
{
    /**
     * Exportar ventas en formato CSV separado por ;
     */
    public function exportSalesCsv(
        ExportSalesCsvRequest $request,
        ExportSalesCsvAction $action
    ): \Illuminate\Http\JsonResponse {
        $filters = ExportSalesCsvFilterData::fromRequest($request->validated());
        $result = $action->execute($filters);

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

        // Construir contenido CSV con separador ;
        $csvContent = implode(';', $headers) . "\n";

        foreach ($result['rows'] as $row) {
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
            ]) . ";\n";
        }

        $filename = 'ventas_' . $filters->date . '.csv';

        try {
            \Illuminate\Support\Facades\Storage::disk('sftp_reports')->put($filename, $csvContent);

            return response()->json([
                'success' => true,
                'message' => "Archivo $filename enviado correctamente al SFTP.",
                'data' => [
                    'filename' => $filename,
                    'rows' => count($result['rows']),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al enviar el archivo al SFTP: ' . $e->getMessage(),
            ], 500);
        }
    }
}
