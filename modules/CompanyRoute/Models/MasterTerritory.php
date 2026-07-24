<?php

declare(strict_types=1);

namespace Modules\CompanyRoute\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MasterTerritory extends Model
{
    protected $table = 'master_territories';
    protected $primaryKey = 'code';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'code',
        'name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function saleZones(): HasMany
    {
        return $this->hasMany(MasterSaleZone::class, 'territory_code', 'code');
    }

    public function companyRoutes(): HasMany
    {
        return $this->hasMany(CompanyRoute::class, 'subregion_code', 'code');
    }
}
