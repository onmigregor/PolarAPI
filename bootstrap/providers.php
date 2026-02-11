<?php

return [
    App\Providers\AppServiceProvider::class,
    Modules\Region\Providers\RegionServiceProvider::class,
    Modules\Auth\Providers\AuthServiceProvider::class,
    Modules\User\Providers\UserServiceProvider::class,
    Modules\Client\Providers\ClientServiceProvider::class,
    Modules\Analytics\Providers\AnalyticsServiceProvider::class,
];
