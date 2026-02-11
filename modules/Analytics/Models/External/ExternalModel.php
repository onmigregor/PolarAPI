<?php

namespace Modules\Analytics\Models\External;

use Illuminate\Database\Eloquent\Model;

/**
 * Base class for models that connect to external tenant databases
 */
abstract class ExternalModel extends Model
{
    /**
     * The connection name for the model.
     * This will be set dynamically based on the tenant
     */
    protected $connection = 'tenant';

    /**
     * Indicates if the model should be timestamped.
     * Most external tables don't follow Laravel conventions
     */
    public $timestamps = false;
}
