<?php

use Illuminate\Support\Facades\Route;
use Modules\CompanyRoute\Http\Controllers\CompanyRouteController;

Route::middleware(['internal_key'])->group(function () {
    Route::middleware('internal_key')->get('company-routes/all', [CompanyRouteController::class, 'listAll']);
    Route::middleware('internal_key')->post('company-routes/sync', [CompanyRouteController::class, 'bulkSync']);
    Route::apiResource('company-routes', CompanyRouteController::class)->parameters([
        'company-routes' => 'company_route'
    ]);
});
