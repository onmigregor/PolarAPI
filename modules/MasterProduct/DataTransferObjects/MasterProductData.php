<?php

namespace Modules\MasterProduct\DataTransferObjects;

class MasterProductData
{
    public function __construct(
        public readonly string $sku,
        public readonly string $name,
        public readonly ?string $category = null,
        public readonly ?string $brand = null,
        public readonly ?string $cl2_code = null,
        public readonly ?string $cl3_code = null,
        public readonly ?string $image = null,
        public readonly bool $is_active = true,
        public readonly ?array $meta_data = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            sku: $data['sku'],
            name: $data['name'],
            category: $data['category'] ?? null,
            brand: $data['brand'] ?? null,
            cl2_code: $data['cl2_code'] ?? null,
            cl3_code: $data['cl3_code'] ?? null,
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
            'category' => $this->category,
            'brand' => $this->brand,
            'cl2_code' => $this->cl2_code,
            'cl3_code' => $this->cl3_code,
            'image' => $this->image,
            'is_active' => $this->is_active,
            'meta_data' => $this->meta_data,
        ], fn($value) => $value !== null);
    }
}
