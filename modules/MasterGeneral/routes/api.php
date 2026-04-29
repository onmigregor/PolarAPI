<?php
declare(strict_types=1);

namespace Modules\MasterGeneral\routes;

use Illuminate\Support\Facades\Route;
use Modules\MasterGeneral\Http\Controllers\MasterGeneralController;

Route::middleware(['internal_key'])->group(function () {
    Route::post('/sync-polar', [MasterGeneralController::class, 'syncPolar']);
});
