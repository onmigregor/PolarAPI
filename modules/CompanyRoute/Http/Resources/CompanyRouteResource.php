<?php

namespace Modules\CompanyRoute\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Region\Http\Resources\RegionResource;

class CompanyRouteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'route_name' => $this->route_name,
            'rif' => $this->rif,
            'description' => $this->description,
            'fiscal_address' => $this->fiscal_address,
            'db_name' => $this->db_name,
            'region' => new RegionResource($this->whenLoaded('region')),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
