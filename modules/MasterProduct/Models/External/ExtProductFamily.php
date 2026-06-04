<?php

namespace Modules\MasterProduct\Models\External;

use Illuminate\Database\Eloquent\Model;

class ExtProductFamily extends Model
{
    protected $connection = 'tenant';
    protected $table = 'product_class1';
    protected $primaryKey = 'id';
    public $timestamps = false;
}
