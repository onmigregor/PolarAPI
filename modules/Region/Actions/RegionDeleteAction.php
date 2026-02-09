<?php

namespace Modules\Region\Actions;

use Illuminate\Http\JsonResponse;
use Modules\Region\Models\Region;
use Symfony\Component\HttpFoundation\Response;

class RegionDeleteAction
{
    public function execute(Region $region): JsonResponse
    {
        $region->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
