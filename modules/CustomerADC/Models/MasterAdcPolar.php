<?php

namespace Modules\CustomerADC\Models;

use Illuminate\Database\Eloquent\Model;

class MasterAdcPolar extends Model
{
    protected $table = 'master_adc_datos_polar';
    protected $primaryKey = 'id_adc';

    protected $fillable = [
        'cus_code',
        'serial',
        'modelo',
        'condicion',
        'descripcion',
        'es_propio',
        'pertenece_a',
        'imagen',
        'ubicacion_imagen',
    ];
}
