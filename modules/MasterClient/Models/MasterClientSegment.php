<?php
namespace Modules\MasterClient\Models;

use Illuminate\Database\Eloquent\Model;

class MasterClientSegment extends Model
{
    protected $table = 'master_clients_segments';
    
    protected $fillable = [
        'tp3_code',
        'tp3_name'
    ];
}
