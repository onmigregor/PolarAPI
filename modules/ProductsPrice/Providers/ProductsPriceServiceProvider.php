<?php

namespace Modules\ProductsPrice\Providers;

use Illuminate\Support\ServiceProvider;

class ProductsPriceServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
    }
}
