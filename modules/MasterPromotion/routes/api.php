<?php

use Illuminate\Support\Facades\Route;
use Modules\MasterPromotion\Http\Controllers\MasterPromotionController;

Route::prefix('master-promotions')->group(function () {
    Route::post('sync-polar', [MasterPromotionController::class, 'syncPolar']);
});
