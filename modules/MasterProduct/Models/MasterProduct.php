<?php

namespace Modules\MasterProduct\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MasterProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'sku',
        'name',
        'pro_short_name',
        'barcode',
        'category',
        'brand',
        'cl1_code',
        'cl2_code',
        'cl3_code',
        'cl4_code',
        'brand_code',
        'segment_code',
        'multiplicity',
        'unt_code',
        'pro_bom_code',
        'pro_return_allowed',
        'pro_damage_returns_allowed',
        'pro_available_for_sale',
        'pro_customer_inventory_allowed',
        'image',
        'is_active',
        'meta_data'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'meta_data' => 'array'
    ];
}
