<?php
declare(strict_types=1);

namespace Modules\MasterGroup\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\MasterGroup\Console\SyncMasterGroups;
use Modules\MasterGroup\Console\AuditProductGroups;

class MasterGroupServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'MasterGroup';

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
        $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');

        if ($this->app->runningInConsole()) {
            $this->commands([
                SyncMasterGroups::class,
                AuditProductGroups::class,
            ]);
        }
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
    }
}
