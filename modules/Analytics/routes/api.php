<?php

use Illuminate\Support\Facades\Route;
use Modules\Analytics\Http\Controllers\AnalyticsController;

Route::middleware(['internal_key'])->prefix('analytics')->group(function () {
    // Filters & Sync
    Route::middleware('internal_key')->get('/filters', [AnalyticsController::class, 'getFilters']);
    Route::middleware('internal_key')->post('/sync-products', [AnalyticsController::class, 'syncProducts']);

    // Reports
    Route::middleware('internal_key')->post('/reports/sales-by-product', [AnalyticsController::class, 'salesByProduct']);
    Route::middleware('internal_key')->post('/reports/top-products', [AnalyticsController::class, 'topProducts']);
    Route::middleware('internal_key')->post('/reports/sales-trend', [AnalyticsController::class, 'salesTrend']);
    Route::middleware('internal_key')->post('/reports/daily-sales-trend', [AnalyticsController::class, 'dailySalesTrend']);
    Route::middleware('internal_key')->post('/reports/sales-by-route', [AnalyticsController::class, 'salesByRoute']);
    Route::middleware('internal_key')->post('/reports/top-groups-by-liters', [AnalyticsController::class, 'topGroupsByLiters']);
    Route::middleware('internal_key')->post('/reports/top-groups-by-kilos', [AnalyticsController::class, 'topGroupsByKilos']);
    Route::middleware('internal_key')->get('/reports/clients-by-tenant', [AnalyticsController::class, 'clientsByTenant']);
});
