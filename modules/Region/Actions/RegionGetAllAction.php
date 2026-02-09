<?php

namespace Modules\Region\Actions;

use Modules\Region\Models\Region;
use Illuminate\Database\Eloquent\Collection;

class RegionGetAllAction
{
    public function execute(): Collection
    {
        return Region::all();
    }
}
