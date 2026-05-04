<?php

namespace Modules\MasterPromotion\Providers;

use Illuminate\Support\ServiceProvider;

class MasterPromotionServiceProvider extends ServiceProvider
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
            \Modules\MasterPromotion\Console\SyncPromotionsToClients::class,
        ]);
    }
}
