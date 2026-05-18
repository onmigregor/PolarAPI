<?php

namespace Modules\MasterDiscount\Models;

use Illuminate\Database\Eloquent\Model;

class MasterDiscountDetail extends Model
{
    protected $table = 'master_discount_details';
    protected $primaryKey = 'did_code';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'did_code',
        'dis_code',
        'did_name',
        'rot_code_customer',
        'cus_code',
        'did_since',
        'did_until',
    ];
}
