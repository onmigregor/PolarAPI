<?php

namespace Modules\MasterProduct\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MasterProductClass3 extends Model
{
    use SoftDeletes;

    protected $table = 'master_product_class_3';

    protected $fillable = [
        'cl3_code',
        'cl2_code',
        'cl3_name',
    ];
}
