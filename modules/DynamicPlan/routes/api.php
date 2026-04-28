<?php
declare(strict_types=1);

namespace Modules\DynamicPlan\routes;

use Illuminate\Support\Facades\Route;
use Modules\DynamicPlan\Http\Controllers\DynamicPlanController;

Route::middleware(['internal_key'])->group(function () {
    Route::middleware('internal_key')->post('/sync-polar', [DynamicPlanController::class, 'syncPolar']);
});
