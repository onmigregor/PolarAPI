<?php

namespace Modules\Region\Actions;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Region\Models\Region;

class RegionGetPaginatedAction
{
    public function execute(array $search, ?int $perPage = null): LengthAwarePaginator
    {
        $limit = $perPage ?? config('apiconfig.pagination.per_page');

        return Region::query()
            ->filter($search)
            ->orderBy('citName')
            ->paginate($limit)
            ->withQueryString();
    }
}
