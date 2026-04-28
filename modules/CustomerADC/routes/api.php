<?php
declare(strict_types=1);

namespace Modules\CustomerADC\routes;

use Illuminate\Support\Facades\Route;
use Modules\CustomerADC\Http\Controllers\CustomerAdcController;

Route::middleware('internal_key')->group(function () {
    Route::middleware('internal_key')->post('/master-customer-adc/sync', [CustomerAdcController::class, 'sync']);
});
