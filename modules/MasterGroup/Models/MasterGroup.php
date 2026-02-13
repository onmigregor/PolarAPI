<?php
declare(strict_types=1);

namespace Modules\MasterGroup\Models;

use Illuminate\Database\Eloquent\Model;

class MasterGroup extends Model
{
    protected $fillable = [
        'name',
        'unit_type',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
