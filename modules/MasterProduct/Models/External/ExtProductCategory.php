<?php

namespace Modules\MasterProduct\Models\External;

use Illuminate\Database\Eloquent\Model;

class ExtProductCategory extends Model
{
    protected $connection = 'tenant';
    protected $table = 'producto_class2';
    protected $primaryKey = 'id';
    public $timestamps = false;
}
