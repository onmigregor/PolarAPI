<?php

namespace Modules\Client\Actions;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Client\Models\Client;

class ClientListAction
{
    public function execute(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Client::query()->with('region');

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
