<?php

namespace Modules\Analytics\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesByRouteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'client_name' => $this['client_name'],
            'route' => $this['route'],
            'total_transactions' => $this['total_transactions'],
            'total_billed_bs' => round($this['total_billed_bs'], 2),
            'total_billed_usd' => round($this['total_billed_usd'], 2),
        ];
    }
}
