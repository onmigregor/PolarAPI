<?php

use Illuminate\Support\Facades\Route;


    // Rutas del módulo MasterProduct
    Route::middleware('internal_key')->post('/master-products/sync', [\Modules\MasterProduct\Http\Controllers\MasterProductController::class, 'syncFromAdmin']);

