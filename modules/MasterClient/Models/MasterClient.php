<?php
declare(strict_types=1);

namespace Modules\MasterClient\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\CompanyRoute\Models\CompanyRoute;

class MasterClient extends Model
{
    protected $fillable = [
        'company_route_id',
        'external_id',
        'cep',
        'cliente',
        'ruta',
        'cus_name',
        'cus_business_name',
        'cus_duns',
        'cus_comm_id',
        'tp1_code',
        'tp2_code',
        'cit_code',
        'txn_code',
        'cus_phone',
        'cus_fax',
        'cus_street1',
        'cus_street2',
        'cus_street3',
        'cus_tax_id1',
        'brc_code',
        'cus_latitude',
        'cus_longitude',
        'prc_code_for_sale',
        'prc_code_for_return',
        'cus_contact_person',
        'cus_email',
    ];

    public function companyRoute()
    {
        return $this->belongsTo(CompanyRoute::class);
    }
}
