<?php

namespace Modules\Report\DataTransferObjects;

use Illuminate\Http\Request;

class BulkImportLogFilterData
{
    public function __construct(
        public array $types = [],
        public ?string $startDate = null,
        public ?string $endDate = null,
        public ?string $search = null,
        public int $page = 1,
        public int $perPage = 10
    ) {}

    public static function fromRequest(Request $request): self
    {
        $typesInput = $request->input('types');
        $types = [];

        if (is_array($typesInput)) {
            $types = array_filter($typesInput, fn($t) => !empty($t) && $t !== 'all');
        } elseif (is_string($typesInput) && !empty($typesInput) && $typesInput !== 'all') {
            $types = array_filter(explode(',', $typesInput), fn($t) => !empty($t) && $t !== 'all');
        }

        $perPage = (int)$request->input('per_page', 10);
        if ($perPage <= 0) $perPage = 10;
        if ($perPage > 100) $perPage = 100;

        $page = (int)$request->input('page', 1);
        if ($page <= 0) $page = 1;

        return new self(
            types: array_values($types),
            startDate: $request->input('start_date'),
            endDate: $request->input('end_date'),
            search: $request->input('search'),
            page: $page,
            perPage: $perPage
        );
    }
}
