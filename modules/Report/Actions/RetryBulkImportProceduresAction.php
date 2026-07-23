<?php

namespace Modules\Report\Actions;

use Modules\Report\Models\BulkImportLog;
use Modules\MasterInvoice\Models\MasterInvoice;
use Modules\MasterInvoice\Actions\DistributeInvoicesToTenantsAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RetryBulkImportProceduresAction
{
    public function execute(int $logId): array
    {
        $log = BulkImportLog::find($logId);
        if (!$log) {
            return [
                'success' => false,
                'message' => "No se encontró el registro de carga #{$logId}.",
            ];
        }

        // Obtener facturas para re-distribuir y ejecutar procedures
        $invoices = MasterInvoice::all()->toArray();
        if (empty($invoices)) {
            return [
                'success' => false,
                'message' => "No hay facturas en el catálogo maestro para ejecutar procedimientos.",
            ];
        }

        $distributor = app(DistributeInvoicesToTenantsAction::class);
        $distResult = $distributor->execute($invoices);

        $proceduresData = $distResult['procedures'] ?? [];
        $procSuccess = !empty($proceduresData);

        foreach ($proceduresData as $tenantProc) {
            if (isset($tenantProc['success']) && !$tenantProc['success']) {
                $procSuccess = false;
                break;
            }
        }

        $statusStr = $procSuccess ? 'completado' : 'fallido';

        DB::table('bulk_import_logs')
            ->where('id', $logId)
            ->update([
                'procedures_status' => $statusStr,
                'procedures_log' => json_encode($proceduresData),
                'updated_at' => now(),
            ]);

        Log::info("RetryBulkImportProceduresAction: Re-ejecutado para log #{$logId}. Estado: {$statusStr}");

        return [
            'success' => $procSuccess,
            'message' => $procSuccess 
                ? 'Procedimientos de inventario re-ejecutados exitosamente en los tenants.' 
                : 'Se re-ejecutaron los procedimientos pero uno o más tenants reportaron error.',
            'data' => $proceduresData,
        ];
    }
}
