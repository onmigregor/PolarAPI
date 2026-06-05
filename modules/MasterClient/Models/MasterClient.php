<?php
declare(strict_types=1);

namespace Modules\MasterClient\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\CompanyRoute\Models\CompanyRoute;

class MasterClient extends Model
{
    protected $table = 'master_client_polar';

    protected $fillable = [
        'company_route_id',
        'cus_code',
        'cus_name',
        'cus_business_name',
        'cus_administrator',
        'tp1_code',
        'tp2_code',
        'cit_code',
        'cus_tax_id1',
        'cus_phone',
        'cus_email',
        'registered_at_tenant',
    ];

    public function companyRoute()
    {
        return $this->belongsTo(CompanyRoute::class);
    }

    public function pools()
    {
        return $this->belongsToMany(
            MasterPool::class,
            'master_customer_pools',
            'cus_code',
            'pol_code',
            'cus_code',
            'pol_code'
        );
    }

    public function scopeFilter($query, array $filters): void
    {
        $query->when($filters['query'] ?? null, function ($q, $search) {
            $q->where(function ($query) use ($search) {
                $query->where('cus_name', 'like', "%{$search}%")
                      ->orWhere('cus_business_name', 'like', "%{$search}%")
                      ->orWhere('cus_administrator', 'like', "%{$search}%")
                      ->orWhere('cus_tax_id1', 'like', "%{$search}%")
                      ->orWhere('cus_code', 'like', "%{$search}%");
            });
        });

        $query->when($filters['tp1_code'] ?? null, function ($q, $tp1) {
            $q->where('tp1_code', $tp1);
        });

        $query->when($filters['tp2_code'] ?? null, function ($q, $tp2) {
            $q->where('tp2_code', $tp2);
        });

        $query->when($filters['cit_code'] ?? null, function ($q, $cit) {
            $q->where('cit_code', $cit);
        });

        if (isset($filters['has_cep'])) {
            $hasCep = filter_var($filters['has_cep'], FILTER_VALIDATE_BOOLEAN);
            if ($hasCep) {
                $query->where(function ($q) {
                    $q->whereNull('cus_code')->orWhere('cus_code', '');
                });
            }
        }
    }
}
