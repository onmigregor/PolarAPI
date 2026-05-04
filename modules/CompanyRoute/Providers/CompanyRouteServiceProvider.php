<?php

namespace Modules\CompanyRoute\Providers;

use Illuminate\Support\ServiceProvider;

class CompanyRouteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->app->register(CompanyRouteRoutingServiceProvider::class);
        $this->registerCommands();
    }

    protected function registerCommands(): void
    {
        $this->commands([
            \Modules\CompanyRoute\Console\SyncRouteMetadata::class,
        ]);
    }
}
