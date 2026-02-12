<?php

namespace Modules\CompanyRoute\Actions;

use Modules\CompanyRoute\DataTransferObjects\CompanyRouteData;
use Modules\CompanyRoute\Models\CompanyRoute;

class CompanyRouteUpdateAction
{
    public function execute(CompanyRoute $companyRoute, CompanyRouteData $data): CompanyRoute
    {
        $companyRoute->update($data->toArray());
        return $companyRoute->refresh();
    }
}
