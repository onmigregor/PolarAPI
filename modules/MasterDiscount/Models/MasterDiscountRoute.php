<?php

namespace Modules\MasterDiscount\Models;

use Illuminate\Database\Eloquent\Model;

class MasterDiscountRoute extends Model
{
    protected $table = 'discount_detail_routes';

    protected $fillable = [
        'rot_code',
        'dis_code',
        'source_file',
        'saved_at',
    ];
}
