<?php

namespace Modules\Analytics\Models\External;

class ExtSale extends ExternalModel
{
    protected $table = 'ventaspxc';
    protected $primaryKey = 'id';

    protected $fillable = [
        'IdVenta',
        'Ruta',
        'Fecha',
        'IdCliente',
        'Cliente',
        'MontoFactura',
        'MontoPendiente',
        'Status',
        'montodivisas',
        'tasa',
    ];

    protected $casts = [
        'Fecha' => 'date',
        'MontoFactura' => 'float',
        'MontoPendiente' => 'float',
        'montodivisas' => 'float',
        'tasa' => 'float',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('not_deleted', function ($query) {
            $query->where('eliminado', 0);
        });
    }

    public function details()
    {
        return $this->hasMany(ExtSaleDetail::class, 'IdVenta', 'IdVenta');
    }
}
