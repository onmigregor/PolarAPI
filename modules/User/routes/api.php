<?php

use Illuminate\Support\Facades\Route;
use Modules\User\Http\Controllers\UserController;

Route::middleware(['internal_key', 'role:admin'])->prefix('users')->group(function () {
    Route::middleware('internal_key')->get('/all', [UserController::class, 'listAll']);
    Route::middleware('internal_key')->get('/roles', [UserController::class, 'roles']);
    Route::middleware('internal_key')->get('/', [UserController::class, 'index']);
    Route::middleware('internal_key')->post('/', [UserController::class, 'store']);
    Route::middleware('internal_key')->get('/{user}', [UserController::class, 'show']);
    Route::put('/{user}', [UserController::class, 'update']);
    Route::patch('/{user}/toggle-status', [UserController::class, 'toggleStatus']);
    Route::delete('/{user}', [UserController::class, 'destroy']);
});
