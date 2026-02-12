<?php

namespace Modules\CompanyRoute\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Region\Models\Region;

class CompanyRoute extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'route_name',
        'rif',
        'description',
        'fiscal_address',
        'region_id',
        'db_name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'region_id' => 'integer',
    ];

    public function region()
    {
        return $this->belongsTo(Region::class);
    }
}
