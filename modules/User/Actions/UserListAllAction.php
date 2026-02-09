<?php

namespace Modules\User\Actions;

use Illuminate\Database\Eloquent\Collection;
use Modules\User\Models\User;

class UserListAllAction
{
    public function execute(): Collection
    {
        return User::with('roles')->orderBy('name')->get();
    }
}
