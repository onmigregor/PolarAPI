<?php

use Illuminate\Support\Facades\Route;
use Modules\Analytics\Http\Controllers\AnalyticsController;

Route::middleware(['auth:sanctum'])->prefix('analytics')->group(function () {
    Route::get('/filters', [AnalyticsController::class, 'getFilters']);
    Route::post('/sync-products', [AnalyticsController::class, 'syncProducts']);
});
