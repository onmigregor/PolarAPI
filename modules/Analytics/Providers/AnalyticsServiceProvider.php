<?php

namespace Modules\Analytics\Providers;

use Illuminate\Support\ServiceProvider;

class AnalyticsServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'Analytics';

    protected string $moduleNameLower = 'analytics';

    public function boot(): void
    {
        $this->registerMigrations();
        $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
        $this->registerCommands();
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
    }

    protected function registerMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../Database/migrations');
    }

    protected function registerCommands(): void
    {
        $this->commands([
            \Modules\Analytics\Console\SyncMasterProducts::class,
            \Modules\Analytics\Console\TestReports::class,
        ]);
    }
}
