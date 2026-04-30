<?php

namespace Modules\MasterCompany\Models;

use Illuminate\Database\Eloquent\Model;

class MasterCompanyTerritory extends Model
{
    protected $table = 'master_company_territories';
    protected $primaryKey = 'try_code';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'try_code',
        'brc_code',
        'lgn_code',
        'try_name',
        'try_email',
    ];
}
