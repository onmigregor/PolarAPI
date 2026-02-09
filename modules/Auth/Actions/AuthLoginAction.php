<?php

namespace Modules\Auth\Actions;

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Modules\User\Models\User;

class AuthLoginAction
{
    public function execute(array $credentials): array
    {
        if (!Auth::attempt($credentials)) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales proporcionadas son incorrectas.'],
            ]);
        }

        $user = User::where('email', $credentials['email'])->with('roles')->first();

        // El token name puede ser cualquier string identificativo
        $token = $user->createToken('auth_token')->plainTextToken;

        return [
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
        ];
    }
}
