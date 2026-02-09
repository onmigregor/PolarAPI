<?php

namespace Modules\Region\Actions;

use Modules\Region\DataTransferObjects\RegionData;
use Modules\Region\Models\Region;

class RegionStoreAction
{
    public function execute(RegionData $data): Region
    {
        return Region::create([
            'citCode' => $data->citCode,
            'citName' => $data->citName,
            'staCode' => $data->staCode,
        ]);
    }
}
