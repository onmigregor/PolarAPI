<?php

namespace Modules\User\Actions;

use Illuminate\Support\Facades\Hash;
use Modules\User\DataTransferObjects\UserData;
use Modules\User\Models\User;

class UserStoreAction
{
    public function execute(UserData $data): User
    {
        $user = User::create([
            'name' => $data->name,
            'email' => $data->email,
            'password' => Hash::make($data->password),
            'active' => $data->active,
        ]);

        if (!empty($data->roles)) {
            $user->roles()->sync($data->roles);
        }

        return $user->load('roles');
    }
}
