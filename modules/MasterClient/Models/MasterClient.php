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
        'cep',
        'cliente',
        'ruta',
        'cus_name',
        'cus_business_name',
        'cus_duns',
        'cus_comm_id',
        'tp1_code',
        'tp2_code',
        'cit_code',
        'txn_code',
        'cus_phone',
        'cus_fax',
        'cus_street1',
        'cus_street2',
        'cus_street3',
        'cus_tax_id1',
        'brc_code',
        'cus_latitude',
        'cus_longitude',
        'prc_code_for_sale',
        'prc_code_for_return',
        'cus_contact_person',
        'cus_email',
        'con_code',
        'cus_credit_limit',
        'cus_balance',
        'fre_week1',
        'fre_week2',
        'fre_week3',
        'fre_week4',
        'fre_customer',
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
            'cep',
            'pol_code'
        );
    }

    public function scopeFilter($query, array $filters): void
    {
        $query->when($filters['query'] ?? null, function ($q, $search) {
            $q->where(function ($query) use ($search) {
                $query->where('cliente', 'like', "%{$search}%")
                      ->orWhere('cus_name', 'like', "%{$search}%")
                      ->orWhere('cus_business_name', 'like', "%{$search}%")
                      ->orWhere('cus_tax_id1', 'like', "%{$search}%")
                      ->orWhere('cep', 'like', "%{$search}%");
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
                    $q->whereNull('cep')->orWhere('cep', '');
                });
            }
        }
    }
}
