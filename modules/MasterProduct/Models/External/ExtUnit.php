<?php

namespace Modules\MasterProduct\Models\External;

use Illuminate\Database\Eloquent\Model;

class ExtUnit extends Model
{
    protected $connection = 'productos_polar';
    protected $table = 'units';
    public $timestamps = false;
}
