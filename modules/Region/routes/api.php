<?php

use Illuminate\Support\Facades\Route;
use Modules\Region\Http\Controllers\RegionController;

Route::get('regions/all', [RegionController::class, 'listAll']);
Route::apiResource('regions', RegionController::class);
