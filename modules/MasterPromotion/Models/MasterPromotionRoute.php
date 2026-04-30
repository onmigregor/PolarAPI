<?php

namespace Modules\MasterPromotion\Models;

use Illuminate\Database\Eloquent\Model;

class MasterPromotionRoute extends Model
{
    protected $table = 'master_promotion_routes';

    protected $fillable = [
        'rot_code',
        'prm_code',
    ];
}
