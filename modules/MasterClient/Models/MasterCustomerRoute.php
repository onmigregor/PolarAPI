<?php

namespace Modules\MasterClient\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterCustomerRoute extends Model
{
    use HasFactory;

    protected $table = 'master_customer_routes';

    protected $fillable = [
        'rot_code',
        'cus_code',
        'fre_code',
        'ctr_monday',
        'ctr_tuesday',
        'ctr_wednesday',
        'ctr_thursday',
        'ctr_friday',
        'ctr_saturday',
        'ctr_sunday',
        'ctr_contact_person',
        'ctr_balance',
        'prc_code_for_sale',
        'con_code',
    ];
}
