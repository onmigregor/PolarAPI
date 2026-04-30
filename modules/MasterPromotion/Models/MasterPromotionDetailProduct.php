<?php

namespace Modules\MasterPromotion\Models;

use Illuminate\Database\Eloquent\Model;

class MasterPromotionDetailProduct extends Model
{
    protected $table = 'master_promotion_detail_products';
    protected $primaryKey = 'prp_code';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'prp_code',
        'pdl_code',
        'prm_code',
        'pro_code',
        'unt_code',
        'cl1code',
        'cl2code',
        'cl3code',
        'cl4code',
        'prp_required',
        'prp_free',
        'prp_quantity1',
        'prp_min_percentage1',
        'prp_min_percentage2',
        'prp_min_free1',
    ];
}
