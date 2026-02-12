<?php

namespace Modules\CompanyRoute\Actions;

use Modules\CompanyRoute\Models\CompanyRoute;

class CompanyRouteDeleteAction
{
    public function execute(CompanyRoute $companyRoute): void
    {
        $companyRoute->delete();
    }
}
