<?php

namespace Modules\Analytics\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TopProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'product_id' => $this['product_id'],
            'product_name' => $this['product_name'],
            'total_quantity' => $this['total_quantity'],
            'total_amount_usd' => round($this['total_amount_usd'], 2),
            'total_amount_bs' => round($this['total_amount_bs'], 2),
        ];
    }
}
