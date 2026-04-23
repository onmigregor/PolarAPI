<?php

namespace Modules\MasterClient\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\CompanyRoute\Models\CompanyRoute;

class MasterClientPolar extends Model
{
    use HasFactory;

    protected $table = 'master_client_polar';

    protected $fillable = [
        'cus_code',
        'cus_name',
        'cus_business_name',
        'cus_administrator',
        'company_route_id',
    ];

    public function companyRoute()
    {
        return $this->belongsTo(CompanyRoute::class);
    }
}
