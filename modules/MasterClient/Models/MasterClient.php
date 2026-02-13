<?php
declare(strict_types=1);

namespace Modules\MasterClient\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\CompanyRoute\Models\CompanyRoute;

class MasterClient extends Model
{
    protected $fillable = [
        'company_route_id',
        'external_id',
        'cliente',
        'ruta',
    ];

    public function companyRoute()
    {
        return $this->belongsTo(CompanyRoute::class);
    }
}
