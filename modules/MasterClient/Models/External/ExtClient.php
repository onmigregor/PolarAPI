<?php
declare(strict_types=1);

namespace Modules\MasterClient\Models\External;

use Modules\Analytics\Models\External\ExternalModel;

class ExtClient extends ExternalModel
{
    protected $table = 'clientes';
    protected $primaryKey = 'IdCliente';

    protected $fillable = [
        'IdCliente',
        'Cliente',
        'Ruta',
    ];
}
