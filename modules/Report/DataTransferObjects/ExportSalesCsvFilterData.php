<?php

namespace Modules\Report\DataTransferObjects;

class ExportSalesCsvFilterData
{
    public function __construct(
        public readonly string $date,
        public readonly ?string $route_code = null,
    ) {}

    public static function fromRequest(array $validated): self
    {
        return new self(
            date: $validated['date'],
            route_code: $validated['route_code'] ?? null,
        );
    }
}
