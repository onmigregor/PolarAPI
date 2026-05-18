<?php

namespace Modules\MasterPromotion\Models;

use Illuminate\Database\Eloquent\Model;

class MasterPromotionDetail extends Model
{
    protected $table = 'master_promotion_details';
    protected $primaryKey = 'pdl_code';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'pdl_code',
        'prm_code',
        'pdl_name',
        'pdl_since',
        'pdl_until',
        'cus_code',
        'rot_code',
        'tp3code',
        'tp1_code',
        'tp2_code',
        'tp3_code',
        'pdl_minimum',
        'unt_code_required',
        'unt_code_free',
        'pdl_order',
        'pdl_scalable',
        'pdl_accumulable',
        'pdl_extended_file',
    ];
}
