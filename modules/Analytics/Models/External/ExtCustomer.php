<?php

namespace Modules\Analytics\Models\External;

class ExtCustomer extends ExternalModel
{
    protected $table = 'clientes';
    protected $primaryKey = 'IdCliente';

    protected $fillable = [
        'Cliente',
        'RIF',
        'Direccion',
        'Ruta',
        'email',
        'TipoCliente',
        'Activo',
    ];

    protected $casts = [
        'Activo' => 'boolean',
    ];
}
