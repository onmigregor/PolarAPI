<?php

namespace Modules\Report\Models;

use Illuminate\Database\Eloquent\Model;

class BulkImportLog extends Model
{
    protected $connection = 'productos_polar';

    protected $table = 'bulk_import_logs';

    protected $fillable = [
        'type',
        'filename',
        'status',
        'progress',
        'summary',
        'error_log',
        'user_id',
        'started_at',
        'finished_at',
        'sync_status',
        'sync_log',
        'procedures_status',
        'procedures_log',
    ];

    protected $casts = [
        'summary' => 'array',
        'sync_log' => 'array',
        'procedures_log' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
