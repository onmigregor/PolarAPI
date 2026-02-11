<?php

namespace Modules\User\Actions;

use Illuminate\Support\Facades\Hash;
use Modules\User\DataTransferObjects\UserData;
use Modules\User\Models\User;

class UserUpdateAction
{
    public function execute(User $user, UserData $data): User
    {
        $updateData = [
            'name' => $data->name,
            'email' => $data->email,
            'active' => $data->active,
        ];

        if (!empty($data->password)) {
            $updateData['password'] = Hash::make($data->password);
        }

        $user->update($updateData);

        if (!empty($data->roles)) {
            $user->roles()->sync($data->roles);
        }

        return $user->load('roles');
    }
}
