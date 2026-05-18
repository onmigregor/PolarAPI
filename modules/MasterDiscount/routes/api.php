<?php

use Illuminate\Support\Facades\Route;
use Modules\MasterDiscount\Http\Controllers\MasterDiscountController;

Route::prefix('master-discounts')->group(function () {
    Route::post('sync-polar', [MasterDiscountController::class, 'syncPolar']);
});
