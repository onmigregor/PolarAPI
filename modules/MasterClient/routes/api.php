<?php
declare(strict_types=1);

namespace Modules\MasterClient\routes;

use Illuminate\Support\Facades\Route;
use Modules\MasterClient\Http\Controllers\MasterClientController;

Route::middleware(['internal_key'])->group(function () {
    Route::get('/master-clients', [MasterClientController::class, 'index']);
    Route::get('/master-clients/filters', [MasterClientController::class, 'getFilters']);
    Route::post('/sync', [MasterClientController::class, 'sync']);
    Route::post('/sync-polar', [MasterClientController::class, 'syncPolar']);
});

