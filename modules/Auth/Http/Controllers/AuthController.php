<?php

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Auth\Actions\AuthLoginAction;
use Modules\Auth\Http\Requests\AuthLoginRequest;

class AuthController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly AuthLoginAction $authLoginAction
    ) {}

    public function login(AuthLoginRequest $request): JsonResponse
    {
        $data = $this->authLoginAction->execute($request->validated());
        return $this->success(new \Modules\Auth\Http\Resources\AuthResource($data), 'Login successful');
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return $this->success(null, 'Logout successful');
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load('roles');
        return $this->success(new \Modules\User\Http\Resources\UserResource($user), 'User data retrieved');
    }
}
