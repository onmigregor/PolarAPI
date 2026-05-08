<?php

namespace Modules\MasterCompany\Models;

use Illuminate\Database\Eloquent\Model;

class MasterCompanyLoginBranch extends Model
{
    protected $table = 'master_company_login_branches';

    protected $fillable = [
        'lgn_code',
        'brc_code',
    ];
}
