<?php

use Illuminate\Support\Facades\Route;
use Modules\Auth\Http\Controllers\AuthController;

Route::middleware('internal_key')->post('auth/login', [AuthController::class, 'login']);

Route::middleware('internal_key')->group(function () {
    Route::middleware('internal_key')->post('auth/logout', [AuthController::class, 'logout']);
    Route::middleware('internal_key')->get('auth/me', [AuthController::class, 'me']);
});
