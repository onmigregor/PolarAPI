<?php

namespace Modules\Analytics\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MasterProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'sku',
        'name',
        'category',
        'brand',
        'image',
        'is_active',
        'meta_data'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'meta_data' => 'array'
    ];
}
