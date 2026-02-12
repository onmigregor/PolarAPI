<?php

namespace Modules\CompanyRoute\Providers;

use Illuminate\Support\ServiceProvider;

class CompanyRouteServiceProvider extends ServiceProvider
{
    public function boot(): void
       {
           $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
          // $this->mergeConfigFrom(__DIR__.'/../config.php', 'companyRouteCategory');

           $this->app->register(CompanyRouteRoutingServiceProvider::class);
       }
}
