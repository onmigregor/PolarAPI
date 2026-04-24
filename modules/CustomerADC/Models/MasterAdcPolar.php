<?php

namespace Modules\CustomerADC\Models;

use Illuminate\Database\Eloquent\Model;

class MasterAdcPolar extends Model
{
    protected $table = 'master_adc_polar';

    protected $fillable = [
        'fq_redi',
        'cus_code',
        'marca',
        'no_serie',
        'no_serial',
        'no_activo',
        'empresa',
        'estado',
        'tipo_activo',
    ];
}
