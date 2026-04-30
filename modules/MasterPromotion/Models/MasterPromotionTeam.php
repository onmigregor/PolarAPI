<?php

namespace Modules\MasterPromotion\Models;

use Illuminate\Database\Eloquent\Model;

class MasterPromotionTeam extends Model
{
    protected $table = 'master_promotion_teams';

    protected $fillable = [
        'tea_code',
        'prm_code',
    ];
}
