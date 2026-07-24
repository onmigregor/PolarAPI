<?php

declare(strict_types=1);

namespace Modules\CompanyRoute\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MasterSaleZone extends Model
{
    protected $table = 'master_sale_zones';
    protected $primaryKey = 'code';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'code',
        'name',
        'territory_code',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function territory(): BelongsTo
    {
        return $this->belongsTo(MasterTerritory::class, 'territory_code', 'code');
    }

    public function companyRoutes(): HasMany
    {
        return $this->hasMany(CompanyRoute::class, 'sale_zone', 'code');
    }
}
