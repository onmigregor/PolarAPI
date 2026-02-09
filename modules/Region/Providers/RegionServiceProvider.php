<?php

namespace Modules\Region\Providers;

use Illuminate\Support\ServiceProvider;

class RegionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        // $this->mergeConfigFrom(__DIR__.'/../config.php', 'clientCategory');
    }
}
