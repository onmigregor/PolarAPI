<?php
declare(strict_types=1);

namespace Modules\MasterClient\routes;

use Illuminate\Support\Facades\Route;
use Modules\MasterClient\Http\Controllers\MasterClientController;

Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/sync', [MasterClientController::class, 'sync']);
});
