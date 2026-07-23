<?php

use Illuminate\Support\Facades\Route;
use Modules\Report\Http\Controllers\ReportController;
use Modules\Report\Http\Controllers\BulkImportLogApiController;

Route::middleware(['internal_key'])->prefix('reports')->group(function () {
    Route::post('/export-csv', [ReportController::class, 'exportSalesCsv']);
    Route::post('/export-adc-consolidated', [ReportController::class, 'exportAdcConsolidated']);
    Route::post('/export-customer-consolidated', [ReportController::class, 'exportCustomerConsolidated']);
    Route::post('/export-ep-requests-csv', [ReportController::class, 'exportEpRequestsCsv']);

    Route::get('/bulk-import-logs', [BulkImportLogApiController::class, 'index']);
    Route::post('/bulk-import-logs/{id}/retry-procedures', [BulkImportLogApiController::class, 'retryProcedures']);
});
