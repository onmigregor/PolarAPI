<?php

namespace Modules\CompanyRoute\Actions;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\CompanyRoute\Models\CompanyRoute;

class CompanyRouteListAction
{
    public function execute(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = CompanyRoute::query()->with('region');

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('rif', 'like', "%{$search}%");
            });
        }

        if (isset($filters['region_id'])) {
            $query->where('region_id', $filters['region_id']);
        }

        return $query->paginate($perPage);
    }
}
