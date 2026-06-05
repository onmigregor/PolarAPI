<?php
declare(strict_types=1);

namespace Modules\MasterClient\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MasterClientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'cep' => $this->cus_code,
            'cliente' => $this->cus_business_name ?? $this->cus_name,
            'ruta' => $this->companyRoute?->route_name,
            'company_route_id' => $this->company_route_id,
            'company_route_name' => $this->companyRoute?->name,
            'company_route_db' => $this->companyRoute?->db_name,
            'cus_name' => $this->cus_name,
            'cus_business_name' => $this->cus_business_name,
            'cus_tax_id1' => $this->cus_tax_id1,
            'tp1_code' => $this->tp1_code,
            'tp2_code' => $this->tp2_code,
            'cit_code' => $this->cit_code,
            'cus_phone' => $this->cus_phone,
            'cus_email' => $this->cus_email,
            'registered_at_tenant' => $this->registered_at_tenant ?? null,
            'created_at' => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,
            'updated_at' => $this->updated_at ? $this->updated_at->format('Y-m-d H:i:s') : null,
        ];
    }
}
