<?php
namespace Modules\MasterClient\Models;

use Illuminate\Database\Eloquent\Model;

class MasterClientBranch extends Model
{
    protected $table = 'master_clients_branches';
    
    protected $fillable = [
        'tp2_code',
        'tp2_name'
    ];
}
