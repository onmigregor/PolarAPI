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
        'cep',
        'name',
        'route_name',
        'zone',
        'rif',
        'description',
        'fiscal_address',
        'region_id',
        'db_name',
        'is_active',
        'is_available_to_sync',
        'address_street1',
        'address_street2',
        'address_street3',
        'subregion_code',
        'sale_zone',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'region_id' => 'integer',
    ];

    public function region()
    {
        return $this->belongsTo(Region::class);
    }

    /**
     * Mutadores para forzar minúsculas
     */
    public function setCodeAttribute($value)
    {
        $this->attributes['code'] = strtolower($value);
    }

    public function setNameAttribute($value)
    {
        $this->attributes['name'] = strtolower($value);
    }

    public function setRouteNameAttribute($value)
    {
        $this->attributes['route_name'] = strtolower($value);
    }

    public function setDbNameAttribute($value)
    {
        $this->attributes['db_name'] = strtolower($value);
    }
}
