<?php

namespace Modules\MasterClient\Models;

use Illuminate\Database\Eloquent\Model;

class MasterPool extends Model
{
    protected $table = 'master_pools';

    protected $fillable = [
        'pol_code',
        'pol_name',
        'pol_customer_search',
        'deleted',
    ];

    protected $casts = [
        'pol_customer_search' => 'boolean',
        'deleted' => 'boolean',
    ];

    public function customers()
    {
        return $this->belongsToMany(
            MasterClient::class,
            'master_customer_pools',
            'pol_code',
            'cus_code',
            'pol_code',
            'cep'
        );
    }
}
