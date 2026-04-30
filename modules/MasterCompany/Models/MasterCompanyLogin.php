<?php

namespace Modules\MasterCompany\Models;

use Illuminate\Database\Eloquent\Model;

class MasterCompanyLogin extends Model
{
    protected $table = 'master_company_logins';
    protected $primaryKey = 'lgn_code';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'lgn_code',
        'lgn_name',
        'brc_code',
        'lgn_phone',
        'lgn_street1',
        'lgn_street2',
        'lgn_street3',
        'srg_code',
    ];
}
