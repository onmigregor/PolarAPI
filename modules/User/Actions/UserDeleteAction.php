<?php

namespace Modules\User\Actions;

use Modules\User\Models\User;

class UserDeleteAction
{
    public function execute(User $user): void
    {
        $user->delete();
    }
}
