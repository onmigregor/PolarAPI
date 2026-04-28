<?php

namespace Modules\MasterInvoice\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;

class MasterInvoiceServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->app->register(MasterInvoiceRoutingServiceProvider::class);
    }
}
