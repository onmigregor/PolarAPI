<?php

namespace Modules\MasterProduct\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MasterProductUnit extends Model
{
    use SoftDeletes;

    protected $table = 'master_product_units';

    protected $fillable = [
        'pro_code',
        'unt_code',
        'pru_divide_by',
        'pru_bar_code',
    ];
}
