<?php
namespace Modules\MasterClient\Models;

use Illuminate\Database\Eloquent\Model;

class MasterClientType1 extends Model
{
    protected $table = 'master_clients_type1';
    
    protected $fillable = [
        'tp1_code',
        'tp1_name'
    ];
}
