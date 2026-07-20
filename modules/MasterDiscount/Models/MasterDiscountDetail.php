<?php

namespace Modules\MasterDiscount\Models;

use Illuminate\Database\Eloquent\Model;

class MasterDiscountDetail extends Model
{
    protected $table = 'discount_details';
    protected $primaryKey = 'did_code';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'did_code',
        'dis_code',
        'did_name',
        'did_order',
        'rot_code_customer',
        'cus_code',
        'tp1code',
        'tp2code',
        'tp3code',
        'unt_code_required',
        'pol_code',
        'did_since',
        'did_until',
        'did_cascade',
        'did_valid_for_return',
        'did_valid_for_sales',
        'source_file',
        'saved_at',
    ];
}
