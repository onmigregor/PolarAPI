<?php

namespace Modules\Analytics\DataTransferObjects;

class ReportFilterData
{
    public function __construct(
        public readonly string $start_date,
        public readonly string $end_date,
        public readonly ?array $client_ids = null,
        public readonly ?array $product_skus = null,
        public readonly ?array $routes = null,
    ) {}

    public static function fromRequest(array $validated): self
    {
        return new self(
            start_date: $validated['start_date'],
            end_date: $validated['end_date'],
            client_ids: $validated['client_ids'] ?? null,
            product_skus: $validated['product_skus'] ?? null,
            routes: $validated['routes'] ?? null,
        );
    }
}
