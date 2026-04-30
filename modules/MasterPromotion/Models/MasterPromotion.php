<?php

namespace Modules\MasterPromotion\Models;

use Illuminate\Database\Eloquent\Model;

class MasterPromotion extends Model
{
    protected $table = 'master_promotions';
    protected $primaryKey = 'prm_code';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'prm_code',
        'prm_name',
        'prm_can_be_disabled',
        'prm_enabled_value_on',
        'prm_valid_to_sale',
    ];
}
