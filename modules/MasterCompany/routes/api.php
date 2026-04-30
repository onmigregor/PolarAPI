<?php

use Illuminate\Support\Facades\Route;
use Modules\MasterCompany\Http\Controllers\MasterCompanyController;

Route::prefix('mastercompany')->middleware('internal_key')->group(function () {
    Route::post('sync-from-admin', [MasterCompanyController::class, 'syncFromAdmin']);
});
