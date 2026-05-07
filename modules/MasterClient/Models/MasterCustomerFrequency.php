<?php

namespace Modules\MasterClient\Models;

use Illuminate\Database\Eloquent\Model;

class MasterCustomerFrequency extends Model
{
    protected $table = 'master_customer_frequencies';

    protected $fillable = [
        'fre_code',
        'fre_name',
        'fre_week1',
        'fre_week2',
        'fre_week3',
        'fre_week4',
        'fre_customer',
    ];
}
