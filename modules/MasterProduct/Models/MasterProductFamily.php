<?php

namespace Modules\MasterProduct\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MasterProductFamily extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'cl1_code',
        'cl1_name',
    ];
}
