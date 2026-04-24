<?php
declare(strict_types=1);

namespace Modules\DynamicPlan\routes;

use Illuminate\Support\Facades\Route;
use Modules\DynamicPlan\Http\Controllers\DynamicPlanController;

Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/sync-polar', [DynamicPlanController::class, 'syncPolar']);
});
