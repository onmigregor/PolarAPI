<?php
declare(strict_types=1);

namespace Modules\MasterClient\routes;

use Illuminate\Support\Facades\Route;
use Modules\MasterClient\Http\Controllers\MasterClientController;

Route::middleware(['internal_key'])->group(function () {
    Route::middleware('internal_key')->post('/sync', [MasterClientController::class, 'sync']);
    Route::middleware('internal_key')->post('/sync-polar', [MasterClientController::class, 'syncPolar']);
});
