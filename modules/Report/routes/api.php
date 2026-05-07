<?php

use Illuminate\Support\Facades\Route;
use Modules\Report\Http\Controllers\ReportController;

Route::middleware(['internal_key'])->prefix('reports')->group(function () {
    Route::post('/export-csv', [ReportController::class, 'exportSalesCsv']);
});
