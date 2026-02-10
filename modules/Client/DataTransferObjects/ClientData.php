<?php

namespace Modules\Client\DataTransferObjects;

use Illuminate\Http\Request;

class ClientData
{
    public function __construct(
        public readonly ?string $code,
        public readonly ?string $name,
        public readonly ?string $rif,
        public readonly ?string $description,
        public readonly ?string $fiscal_address,
        public readonly ?int $region_id,
        public readonly ?string $db_name,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            code: $request->input('code'),
            name: $request->input('name'),
            rif: $request->input('rif'),
            description: $request->input('description'),
            fiscal_address: $request->input('fiscal_address'),
            region_id: $request->has('region_id') ? (int) $request->input('region_id') : null,
            db_name: $request->input('db_name'),
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'code' => $this->code,
            'name' => $this->name,
            'rif' => $this->rif,
            'description' => $this->description,
            'fiscal_address' => $this->fiscal_address,
            'region_id' => $this->region_id,
            'db_name' => $this->db_name,
        ], fn($value) => !is_null($value));
    }
}
