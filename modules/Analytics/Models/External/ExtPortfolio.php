<?php

namespace Modules\Analytics\Models\External;

class ExtPortfolio extends ExternalModel
{
    protected $table = 'portafolio';
    protected $primaryKey = 'idportafolio';

    protected $fillable = [
        'ruta',
        'idproducto',
        'stock',
        'stock_min',
        'precioventa',
        'activo',
    ];

    protected $casts = [
        'stock' => 'integer',
        'stock_min' => 'integer',
        'precioventa' => 'float',
        'activo' => 'boolean',
    ];

    public function product()
    {
        return $this->belongsTo(ExtProduct::class, 'idproducto', 'idproducto');
    }
}
