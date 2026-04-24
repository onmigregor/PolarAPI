<?php
declare(strict_types=1);

namespace Modules\CustomerADC\routes;

use Illuminate\Support\Facades\Route;
use Modules\CustomerADC\Http\Controllers\CustomerAdcController;

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/master-customer-adc/sync', [CustomerAdcController::class, 'sync']);
});
