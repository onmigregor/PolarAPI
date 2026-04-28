<?php

use Illuminate\Support\Facades\Route;
use Modules\Region\Http\Controllers\RegionController;

Route::middleware('internal_key')->get('regions/all', [RegionController::class, 'listAll']);
Route::apiResource('regions', RegionController::class);
