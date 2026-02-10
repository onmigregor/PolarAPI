<?php

use Illuminate\Support\Facades\Route;
use Modules\Client\Http\Controllers\ClientController;

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('clients/all', [ClientController::class, 'listAll']);
    Route::apiResource('clients', ClientController::class);
});
