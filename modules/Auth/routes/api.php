<?php

use Illuminate\Support\Facades\Route;
use Modules\Auth\Http\Controllers\AuthController;

Route::middleware('internal_key')->post('auth/login', [AuthController::class, 'login']);

Route::middleware(['internal_key', 'auth:sanctum'])->group(function () {
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('auth/me', [AuthController::class, 'me']);
});
