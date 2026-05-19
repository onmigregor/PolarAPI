<?php
namespace Modules\MasterClient\Models;

use Illuminate\Database\Eloquent\Model;

class MasterClientType2 extends Model
{
    protected $table = 'master_clients_type2';
    
    protected $fillable = [
        'tp2_code',
        'tp2_name'
    ];
}
