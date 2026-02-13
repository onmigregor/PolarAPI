<?php
declare(strict_types=1);

namespace Modules\MasterClient\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\MasterClient\Console\SyncMasterClients;

class MasterClientServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'MasterClient';

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
        $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');

        if ($this->app->runningInConsole()) {
            $this->commands([
                SyncMasterClients::class,
            ]);
        }
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
    }
}
