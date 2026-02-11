<?php

use Illuminate\Support\Facades\Route;
use Modules\Analytics\Http\Controllers\AnalyticsController;

Route::middleware(['auth:sanctum'])->prefix('analytics')->group(function () {
    // Filters & Sync
    Route::get('/filters', [AnalyticsController::class, 'getFilters']);
    Route::post('/sync-products', [AnalyticsController::class, 'syncProducts']);

    // Reports
    Route::post('/reports/sales-by-product', [AnalyticsController::class, 'salesByProduct']);
    Route::post('/reports/top-products', [AnalyticsController::class, 'topProducts']);
    Route::post('/reports/sales-trend', [AnalyticsController::class, 'salesTrend']);
    Route::post('/reports/sales-by-route', [AnalyticsController::class, 'salesByRoute']);
});
