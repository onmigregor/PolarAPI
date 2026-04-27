<?php

use Illuminate\Support\Facades\Route;


    // Rutas del módulo MasterProduct
    Route::post('/master-products/sync', [\Modules\MasterProduct\Http\Controllers\MasterProductController::class, 'syncFromAdmin']);

