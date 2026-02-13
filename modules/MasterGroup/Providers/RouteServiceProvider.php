<?php
declare(strict_types=1);

namespace Modules\MasterGroup\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    public function map(): void
    {
        Route::prefix('api/master-groups')
            ->middleware('api')
            ->group(__DIR__ . '/../routes/api.php');
    }
}
