<?php

namespace Modules\Analytics\DataTransferObjects;

class ReportFilterData
{
    public function __construct(
        public readonly string $start_date,
        public readonly string $end_date,
        public readonly ?array $region_ids = null,
        /** @var array|null Array of MasterClient external_ids (CodCliente in tenant DB) */
        public readonly ?array $client_ids = null,
        public readonly ?array $product_skus = null,
        /** @var array|null Array of CompanyRoute IDs (Tenants) */
        public readonly ?array $routes = null,
        public readonly ?array $cl1_codes = null,
        public readonly ?array $cl2_codes = null,
        public readonly ?array $brand_codes = null,
        public readonly ?array $cl3_codes = null,
        public readonly ?array $fq_codes = null,
        public readonly ?array $vendor_groups = null,
        public readonly ?array $offices = null,
        public readonly ?array $territories = null,
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
            cl3_codes: $validated['cl3_codes'] ?? null,
            fq_codes: $validated['fq_codes'] ?? null,
            vendor_groups: $validated['vendor_groups'] ?? null,
            offices: $validated['offices'] ?? null,
            territories: $validated['territories'] ?? null,
        );
    }
}
