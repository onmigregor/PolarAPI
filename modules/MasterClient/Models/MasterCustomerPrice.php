<?php

namespace Modules\MasterClient\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterCustomerPrice extends Model
{
    use HasFactory;

    protected $table = 'master_customer_prices';

    protected $fillable = [
        'rot_code',
        'cus_code',
        'prc_code',
        'csp_for_sale',
        'csp_for_return',
    ];

    protected $casts = [
        'csp_for_sale'   => 'boolean',
        'csp_for_return' => 'boolean',
    ];
}
