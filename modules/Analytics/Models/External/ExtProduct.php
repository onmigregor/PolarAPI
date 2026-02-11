<?php

namespace Modules\Analytics\Models\External;

class ExtProduct extends ExternalModel
{
    protected $table = 'productos';
    protected $primaryKey = 'idproducto';

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
    ];

    protected $casts = [
        'producto_activo' => 'boolean',
        'precioventa' => 'float',
        'preciocompra' => 'float',
    ];
}
