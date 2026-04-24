<?php

namespace Modules\CustomerADC\Providers;

use Illuminate\Support\ServiceProvider;

class CustomerADCServiceProvider extends ServiceProvider
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
