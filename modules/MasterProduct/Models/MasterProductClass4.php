<?php

namespace Modules\MasterProduct\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MasterProductClass4 extends Model
{
    use SoftDeletes;

    protected $table = 'master_product_class_4';

    protected $fillable = [
        'cl4_code',
        'cl3_code',
        'cl4_name',
        'brand_code',
        'segment_code',
    ];
}
