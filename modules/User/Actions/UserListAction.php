<?php

namespace Modules\User\Actions;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\User\Models\User;

class UserListAction
{
    public function execute(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return User::query()
            ->when($filters['query'] ?? $filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($filters['role'] ?? null, function ($query, $role) {
                $query->whereHas('roles', function ($q) use ($role) {
                    $q->where('name', $role);
                });
            })
            ->with('roles')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }
}
