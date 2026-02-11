<?php

namespace Modules\Analytics\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesTrendResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'year' => $this['year'],
            'month' => $this['month'],
            'total_transactions' => $this['total_transactions'],
            'total_billed_bs' => round($this['total_billed_bs'], 2),
            'total_billed_usd' => round($this['total_billed_usd'], 2),
            'total_pending' => round($this['total_pending'], 2),
        ];
    }
}
