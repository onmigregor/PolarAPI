<?php

namespace Modules\MasterDiscount\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MasterDiscount extends Model
{
    protected $table = 'master_discounts';
    protected $primaryKey = 'dis_code';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'dis_code',
        'dis_name',
    ];
}
