<?php

namespace Modules\Analytics\Models\External;

class ExtSaleDetail extends ExternalModel
{
    protected $table = 'ventas_detalle';
    protected $primaryKey = 'idventa_detalle';

    protected $fillable = [
        'IdVenta',
        'idproducto',
        'producto',
        'cantidad',
        'precioventa',
        'descuento',
        'ruta',
        'fecha',
    ];

    protected $casts = [
        'cantidad' => 'integer',
        'precioventa' => 'float',
        'descuento' => 'float',
        'fecha' => 'date',
    ];

    public function sale()
    {
        return $this->belongsTo(ExtSale::class, 'IdVenta', 'IdVenta');
    }

    public function product()
    {
        return $this->belongsTo(ExtProduct::class, 'idproducto', 'idproducto');
    }
}
