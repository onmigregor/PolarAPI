<?php
declare(strict_types=1);

namespace Modules\MasterGeneral\Providers;

use Illuminate\Support\ServiceProvider;

class MasterGeneralServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'MasterGeneral';

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
    }
}
