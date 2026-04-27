<?php

namespace Modules\MasterProduct\DataTransferObjects;

class MasterProductData
{
    public function __construct(
        public readonly string $sku,
        public readonly string $name,
        public readonly ?string $pro_short_name = null,
        public readonly ?string $barcode = null,
        public readonly ?string $category = null,
        public readonly ?string $brand = null,
        public readonly ?string $cl2_code = null,
        public readonly ?string $cl3_code = null,
        public readonly ?string $cl4_code = null,
        public readonly ?string $brand_code = null,
        public readonly ?string $segment_code = null,
        public readonly int $multiplicity = 1,
        public readonly ?string $image = null,
        public readonly bool $is_active = true,
        public readonly ?array $meta_data = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            sku: $data['sku'],
            name: $data['name'],
            pro_short_name: $data['pro_short_name'] ?? null,
            barcode: $data['barcode'] ?? null,
            category: $data['category'] ?? null,
            brand: $data['brand'] ?? null,
            cl2_code: $data['cl2_code'] ?? null,
            cl3_code: $data['cl3_code'] ?? null,
            cl4_code: $data['cl4_code'] ?? null,
            brand_code: $data['brand_code'] ?? null,
            segment_code: $data['segment_code'] ?? null,
            multiplicity: $data['multiplicity'] ?? 1,
            image: $data['image'] ?? null,
            is_active: $data['is_active'] ?? true,
            meta_data: $data['meta_data'] ?? null,
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'sku' => $this->sku,
            'name' => $this->name,
            'pro_short_name' => $this->pro_short_name,
            'barcode' => $this->barcode,
            'category' => $this->category,
            'brand' => $this->brand,
            'cl2_code' => $this->cl2_code,
            'cl3_code' => $this->cl3_code,
            'cl4_code' => $this->cl4_code,
            'brand_code' => $this->brand_code,
            'segment_code' => $this->segment_code,
            'multiplicity' => $this->multiplicity,
            'image' => $this->image,
            'is_active' => $this->is_active,
            'meta_data' => $this->meta_data,
        ], fn($value) => $value !== null);
    }
}
