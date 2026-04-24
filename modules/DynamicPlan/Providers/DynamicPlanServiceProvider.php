<?php
declare(strict_types=1);

namespace Modules\DynamicPlan\Providers;

use Illuminate\Support\ServiceProvider;

class DynamicPlanServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'DynamicPlan';

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
    }
}
