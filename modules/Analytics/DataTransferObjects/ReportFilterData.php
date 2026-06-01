<?php

namespace Modules\Analytics\DataTransferObjects;

class ReportFilterData
{
    public function __construct(
        public readonly string $start_date,
        public readonly string $end_date,
        public readonly ?array $region_ids = null,
        public readonly ?array $client_ids = null,
        public readonly ?array $product_skus = null,
        public readonly ?array $routes = null,
        public readonly ?array $cl1_codes = null,
        public readonly ?array $cl2_codes = null,
        public readonly ?array $brand_codes = null,
        public readonly ?array $segment_codes = null,
    ) {}

    public static function fromRequest(array $validated): self
    {
        return new self(
            start_date: $validated['start_date'],
            end_date: $validated['end_date'],
            region_ids: $validated['region_ids'] ?? null,
            client_ids: $validated['client_ids'] ?? null,
            product_skus: $validated['product_skus'] ?? null,
            routes: $validated['routes'] ?? null,
            cl1_codes: $validated['cl1_codes'] ?? null,
            cl2_codes: $validated['cl2_codes'] ?? null,
            brand_codes: $validated['brand_codes'] ?? null,
            segment_codes: $validated['segment_codes'] ?? null,
        );
    }
}
