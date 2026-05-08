<?php

namespace Modules\MasterProduct\Models\External;

use Illuminate\Database\Eloquent\Model;

class ExtProductClass3 extends Model
{
    protected $connection = 'tenant';
    protected $table = 'producto_class3';
    protected $primaryKey = 'id';
    public $timestamps = false;
}
