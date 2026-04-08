<?php

namespace Modules\MasterProduct\Providers;

use Illuminate\Support\ServiceProvider;

class MasterProductServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->app->register(MasterProductRoutingServiceProvider::class);
        $this->registerCommands();
    }

    protected function registerCommands(): void
    {
        $this->commands([
            \Modules\MasterProduct\Console\SyncMasterProducts::class,
            \Modules\MasterProduct\Console\SyncClientProducts::class,
            \Modules\MasterProduct\Console\SyncMasterToClients::class,
        ]);
    }
}
