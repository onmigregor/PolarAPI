<?php

use Illuminate\Support\Facades\Route;
use Modules\User\Http\Controllers\UserController;

Route::middleware(['auth:sanctum', 'role:admin'])->prefix('users')->group(function () {
    Route::get('/all', [UserController::class, 'listAll']);
    Route::get('/roles', [UserController::class, 'roles']);
    Route::get('/', [UserController::class, 'index']);
    Route::post('/', [UserController::class, 'store']);
    Route::get('/{user}', [UserController::class, 'show']);
    Route::put('/{user}', [UserController::class, 'update']);
    Route::patch('/{user}/toggle-status', [UserController::class, 'toggleStatus']);
    Route::delete('/{user}', [UserController::class, 'destroy']);
});
