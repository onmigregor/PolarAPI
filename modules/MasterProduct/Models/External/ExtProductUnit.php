<?php

namespace Modules\MasterProduct\Models\External;

use Illuminate\Database\Eloquent\Model;

class ExtProductUnit extends Model
{
    protected $connection = 'productos_polar';
    protected $table = 'product_units';
    public $timestamps = false;
}
