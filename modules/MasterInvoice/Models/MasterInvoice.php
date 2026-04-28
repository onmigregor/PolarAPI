<?php

namespace Modules\MasterInvoice\Models;

use Illuminate\Database\Eloquent\Model;

class MasterInvoice extends Model
{
    protected $table = 'master_invoices';

    protected $fillable = [
        'fq_redi',
        'fecha_creacion',
        'codigo_polar_negocio',
        'no_factura',
        'no_control',
        'zona_venta',
        'material',
        'cantidad',
        'um',
        'precio',
        'iva',
        'descuento',
        'otro_margen',
        'envases',
        'lisaea_unidad',
        'tasa',
    ];
}
