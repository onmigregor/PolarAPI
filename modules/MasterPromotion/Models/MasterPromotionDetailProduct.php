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
        'prp_valid_for_base_percentage',
        'prp_quantity',
        'prp_quantity1',
        'prp_quantity2',
        'prp_quantity3',
        'prp_quantity4',
        'prp_quantity5',
        'prp_min_percentage1',
        'prp_min_percentage2',
        'prp_min_percentage3',
        'prp_min_percentage4',
        'prp_min_percentage5',
        'prp_max_percentage2',
        'prp_max_percentage3',
        'prp_max_percentage4',
        'prp_max_percentage5',
        'prp_min_free1',
        'prp_min_free2',
        'prp_min_free3',
        'prp_min_free4',
        'prp_min_free5',
        'prp_max_free1',
        'prp_max_free2',
        'prp_max_free3',
        'prp_max_free4',
        'unt_code_free',
    ];
}
