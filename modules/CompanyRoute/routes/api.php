<?php

use Illuminate\Support\Facades\Route;
use Modules\CompanyRoute\Http\Controllers\CompanyRouteController;

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('company-routes/all', [CompanyRouteController::class, 'listAll']);
    Route::post('company-routes/sync', [CompanyRouteController::class, 'bulkSync']);
    Route::apiResource('company-routes', CompanyRouteController::class)->parameters([
        'company-routes' => 'company_route'
    ]);
});
