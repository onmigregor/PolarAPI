<?php
declare(strict_types=1);

namespace Modules\MasterClient\Actions;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\MasterClient\Models\MasterClient;

class MasterClientGetPaginatedAction
{
    public function execute(array $filters, ?int $perPage = null): LengthAwarePaginator
    {
        $limit = $perPage ?? (int)config('apiconfig.pagination.per_page', 10);

        return MasterClient::query()
            ->filter($filters)
            ->orderBy('cus_name')
            ->paginate($limit)
            ->withQueryString();
    }
}
