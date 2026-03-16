<?php

namespace Modules\MasterProduct\Models\External;

use Illuminate\Database\Eloquent\Model;

class ExtClientProduct extends Model
{
    protected $connection = 'tenant';
    protected $table = 'productos';
    protected $primaryKey = 'idproducto';
    public $timestamps = false;

    protected $fillable = [
        'producto',
        'descripcion1',
        'descripcion2',
        'imagen',
        'grupo',
        'categoria',
        'tipo',
        'marca',
        'codigoSKU',
        'codigobarras',
        'precioventa',
        'preciocompra',
        'producto_activo',
        'unidadesporcaja',
    ];

    protected $casts = [
        'producto_activo' => 'boolean',
        'precioventa' => 'float',
        'preciocompra' => 'float',
        'unidadesporcaja' => 'integer',
    ];
}
