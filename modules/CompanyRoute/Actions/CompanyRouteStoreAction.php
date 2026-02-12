<?php

namespace Modules\CompanyRoute\Actions;

use Modules\CompanyRoute\DataTransferObjects\CompanyRouteData;
use Modules\CompanyRoute\Models\CompanyRoute;

class CompanyRouteStoreAction
{
    public function execute(CompanyRouteData $data): CompanyRoute
    {
        return CompanyRoute::create($data->toArray());
    }
}
