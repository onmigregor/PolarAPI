<?php

use Illuminate\Support\Facades\Route;
use Modules\Report\Http\Controllers\ReportController;

Route::middleware(['auth:sanctum'])->prefix('reports')->group(function () {
    Route::get('/export-sales-csv', [ReportController::class, 'exportSalesCsv']);
});
