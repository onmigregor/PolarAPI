<?php

namespace Modules\ProductsPrice\Models;

use Illuminate\Database\Eloquent\Model;

class MasterProductsPrice extends Model
{
    protected $table = 'master_products_price_polar';

    protected $fillable = [
        'lgnstreet1',
        'material',
        'descripcion',
        'precio_compra_caja_con_iva',
    ];
}
