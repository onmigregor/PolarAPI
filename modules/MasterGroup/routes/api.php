<?php
declare(strict_types=1);

namespace Modules\MasterGroup\routes;

use Illuminate\Support\Facades\Route;
use Modules\MasterGroup\Http\Controllers\MasterGroupController;

Route::post('/sync', [MasterGroupController::class, 'sync']);
Route::post('/audit', [MasterGroupController::class, 'audit']);
