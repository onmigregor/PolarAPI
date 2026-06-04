<?php

use Illuminate\Support\Facades\Route;
use Modules\MasterDiscount\Http\Controllers\MasterDiscountController;

Route::prefix('master-discounts')->middleware('internal_key')->group(function () {
    Route::post('sync-polar', [MasterDiscountController::class, 'syncPolar']);
});
