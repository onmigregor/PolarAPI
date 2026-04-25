<?php
declare(strict_types=1);

namespace Modules\ProductsPrice\routes;

use Illuminate\Support\Facades\Route;
use Modules\ProductsPrice\Http\Controllers\ProductsPriceController;

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/master-products-price/sync', [ProductsPriceController::class, 'sync']);
});
