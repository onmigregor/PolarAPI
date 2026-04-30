<?php

namespace Modules\MasterCompany\Providers;

use Illuminate\Support\ServiceProvider;

class MasterCompanyServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->register(MasterCompanyRoutingServiceProvider::class);
    }
}
