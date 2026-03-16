<?php

namespace Modules\MasterProduct\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MasterUnit extends Model
{
    use SoftDeletes;

    protected $table = 'master_units';

    protected $fillable = [
        'unt_code',
        'unt_name',
        'unt_nick',
    ];
}
