<?php

namespace Modules\MasterDiscount\Providers;

use Illuminate\Support\ServiceProvider;

class MasterDiscountServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->app->register(RouteServiceProvider::class);
        $this->registerCommands();
    }

    protected function registerCommands(): void
    {
        $this->commands([
            \Modules\MasterDiscount\Console\SyncDiscountsToClients::class,
        ]);
    }
}
