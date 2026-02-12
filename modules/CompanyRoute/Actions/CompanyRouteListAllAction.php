<?php

namespace Modules\CompanyRoute\Actions;

use Illuminate\Database\Eloquent\Collection;
use Modules\CompanyRoute\Models\CompanyRoute;

class CompanyRouteListAllAction
{
    public function execute(): Collection
    {
        return CompanyRoute::with('region')->orderBy('name')->get();
    }
}
