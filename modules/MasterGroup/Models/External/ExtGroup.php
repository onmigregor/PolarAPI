<?php
declare(strict_types=1);

namespace Modules\MasterGroup\Models\External;

use Modules\Analytics\Models\External\ExternalModel;

class ExtGroup extends ExternalModel
{
    protected $table = 'grupos';
    protected $primaryKey = 'idgroup';

    protected $fillable = [
        'nameGroup',
    ];
}
