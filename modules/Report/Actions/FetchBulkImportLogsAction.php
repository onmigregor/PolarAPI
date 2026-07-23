<?php

namespace Modules\Report\Actions;

use Modules\Report\DataTransferObjects\BulkImportLogFilterData;
use Modules\Report\Models\BulkImportLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class FetchBulkImportLogsAction
{
    public function execute(BulkImportLogFilterData $filters): LengthAwarePaginator
    {
        $query = BulkImportLog::query();

        // 1. Filter by multiple types if present
        if (!empty($filters->types)) {
            $query->whereIn('type', $filters->types);
        }

        // 2. Filter by date range (created_at / started_at)
        if (!empty($filters->startDate)) {
            $query->whereDate('created_at', '>=', $filters->startDate);
        }
        if (!empty($filters->endDate)) {
            $query->whereDate('created_at', '<=', $filters->endDate);
        }

        // 3. Filter by search string (filename)
        if (!empty($filters->search)) {
            $searchTerm = trim($filters->search);
            $query->where('filename', 'LIKE', "%{$searchTerm}%");
        }

        // Order by latest ID descending
        $query->orderByDesc('id');

        return $query->paginate(
            perPage: $filters->perPage,
            page: $filters->page
        );
    }
}
