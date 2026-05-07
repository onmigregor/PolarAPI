<?php

namespace Modules\Report\DataTransferObjects;

class ExportSalesCsvFilterData
{
    public function __construct(
        public readonly string $start_date,
        public readonly ?string $end_date = null,
        public readonly ?string $route_code = null,
    ) {}

    public static function fromRequest(array $validated): self
    {
        return new self(
            start_date: $validated['start_date'] ?? $validated['date'],
            end_date: $validated['end_date'] ?? null,
            route_code: $validated['route_code'] ?? null,
        );
    }
}
