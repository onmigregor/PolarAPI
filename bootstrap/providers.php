<?php

return [
    App\Providers\AppServiceProvider::class,
    Modules\Region\Providers\RegionServiceProvider::class,
    Modules\Auth\Providers\AuthServiceProvider::class,
    Modules\User\Providers\UserServiceProvider::class,
    Modules\CompanyRoute\Providers\CompanyRouteServiceProvider::class,
    Modules\Analytics\Providers\AnalyticsServiceProvider::class,
    Modules\MasterGroup\Providers\MasterGroupServiceProvider::class,
];
