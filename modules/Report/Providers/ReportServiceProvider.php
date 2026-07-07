<?php

namespace Modules\Report\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Report\Console\GenerateDailySalesReportsCommand;
use Modules\Report\Console\ExportCustomerConsolidatedCommand;

class ReportServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
       // $this->mergeConfigFrom(__DIR__.'/../config.php', 'clientCategory');

        $this->app->register(RouteServiceProvider::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateDailySalesReportsCommand::class,
                ExportCustomerConsolidatedCommand::class,
            ]);
        }
    }
}
