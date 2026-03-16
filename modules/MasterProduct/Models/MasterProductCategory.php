<?php

namespace Modules\MasterProduct\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MasterProductCategory extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'cl2_code',
        'cl1_code',
        'cl2_name',
    ];
}
