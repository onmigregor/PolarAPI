<?php
declare(strict_types=1);

namespace Modules\MasterClient\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MasterClientExportResource extends JsonResource
{
    /**
     * Transform the resource into an array with the 10 requested export columns.
     *
     * @param \Illuminate\Http\Request $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'cep'               => $this->cep ?? $this->cus_code ?? '',
            'cliente'           => $this->cliente ?? $this->cus_name ?? '',
            'cus_business_name' => $this->cus_business_name ?? $this->cliente ?? $this->cus_name ?? '',
            'cus_tax_id1'       => $this->cus_tax_id1 ?? '',
            'tp2_code'          => $this->tp2_code ?? '',
            'direccion'         => $this->direccion ?? '',
            'cus_phone'         => $this->cus_phone ?? '',
            'zona_venta'        => $this->zona_venta ?? $this->companyRoute?->sale_zone ?? '',
            'oficina'           => $this->oficina ?? $this->companyRoute?->address_street1 ?? '',
            'territorio'        => $this->territorio ?? $this->companyRoute?->subregion_code ?? '',
        ];
    }

    /**
     * Get ordered headers array matching the columns.
     *
     * @return array<int, string>
     */
    public static function headers(): array
    {
        return [
            'Código Cep',
            'Nombre',
            'Razón Social',
            'Rif',
            'Tipo de cliente',
            'Dirección física',
            'Teléfono',
            'Zona de venta',
            'Oficina',
            'Territorio',
        ];
    }
}
