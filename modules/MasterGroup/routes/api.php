<?php
declare(strict_types=1);

namespace Modules\MasterGroup\routes;

use Illuminate\Support\Facades\Route;
use Modules\MasterGroup\Http\Controllers\MasterGroupController;

Route::middleware('internal_key')->post('/sync', [MasterGroupController::class, 'sync']);
Route::middleware('internal_key')->post('/audit', [MasterGroupController::class, 'audit']);
