<?php

namespace Modules\MasterCompany\Models;

use Illuminate\Database\Eloquent\Model;

class MasterCompanyBranch extends Model
{
    protected $table = 'master_company_branches';

    protected $fillable = [
        'brc_code',
        'brc_name',
        'brc_general_header1',
        'reg_code',
    ];
}
