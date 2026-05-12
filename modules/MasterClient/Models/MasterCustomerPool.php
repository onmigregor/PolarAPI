<?php

namespace Modules\MasterClient\Models;

use Illuminate\Database\Eloquent\Model;

class MasterCustomerPool extends Model
{
    protected $table = 'master_customer_pools';

    protected $fillable = [
        'cus_code',
        'pol_code',
        'deleted',
    ];

    protected $casts = [
        'deleted' => 'boolean',
    ];

    public function client()
    {
        return $this->belongsTo(MasterClient::class, 'cus_code', 'cep');
    }

    public function pool()
    {
        return $this->belongsTo(MasterPool::class, 'pol_code', 'pol_code');
    }
}
