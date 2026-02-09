<?php

namespace Modules\Region\Actions;

use Modules\Region\DataTransferObjects\RegionData;
use Modules\Region\Models\Region;

class RegionUpdateAction
{
    public function execute(RegionData $data, Region $region): Region
    {
        $region->update([
            'citCode' => $data->citCode,
            'citName' => $data->citName,
            'staCode' => $data->staCode,
        ]);

        return $region;
    }
}
