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

    protected static function booted(): void
    {
        static::addGlobalScope('not_deleted', function ($query) {
            $query->where('eliminado', 0);
        });
    }

    public function sale()
    {
        return $this->belongsTo(ExtSale::class, 'IdVenta', 'IdVenta');
    }

    public function product()
    {
        return $this->belongsTo(ExtProduct::class, 'idproducto', 'idproducto');
    }
}
