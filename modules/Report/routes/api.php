<?php

use Illuminate\Support\Facades\Route;
use Modules\Report\Http\Controllers\ReportController;

Route::middleware(['internal_key'])->prefix('reports')->group(function () {
    Route::middleware('internal_key')->get('/export-sales-csv', [ReportController::class, 'exportSalesCsv']);
});
