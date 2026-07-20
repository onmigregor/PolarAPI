<?php

namespace Modules\MasterDiscount\Models;

use Illuminate\Database\Eloquent\Model;

class MasterDiscount extends Model
{
    protected $table = 'discounts';
    protected $primaryKey = 'dis_code';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'dis_code',
        'dis_name',
        'dis_can_be_disabled',
        'dis_enabled_value_on',
        'dis_disable_for_detail',
        'source_file',
        'saved_at',
    ];
}
