<?php

namespace Modules\User\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\User\Actions\UserDeleteAction;
use Modules\User\Actions\UserListAction;
use Modules\User\Actions\UserListAllAction;
use Modules\User\Actions\UserStoreAction;
use Modules\User\Actions\UserUpdateAction;
use Modules\User\Http\Requests\UserRequest;
use Modules\User\Http\Resources\UserResource;
use Modules\User\Models\User;

class UserController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly UserListAction $listAction,
        private readonly UserListAllAction $listAllAction,
        private readonly UserStoreAction $storeAction,
        private readonly UserUpdateAction $updateAction,
        private readonly UserDeleteAction $deleteAction
    ) {}

    public function index(Request $request): JsonResponse
    {
        $users = $this->listAction->execute(
            $request->all(),
            $request->get('per_page', 15)
        );

        return $this->success(UserResource::collection($users), 'Users retrieved successfully');
    }

    public function listAll(): JsonResponse
    {
        $users = $this->listAllAction->execute();
        return $this->success(UserResource::collection($users), 'All users retrieved successfully');
    }

    public function roles(): JsonResponse
    {
        $roles = \Modules\User\Models\Role::all();
        return $this->success(\Modules\User\Http\Resources\RoleResource::collection($roles), 'Roles retrieved successfully');
    }

    public function store(UserRequest $request): JsonResponse
    {
        $data = \Modules\User\DataTransferObjects\UserData::fromRequest($request);
        $user = $this->storeAction->execute($data);
        return $this->success(new UserResource($user), 'User created successfully', 201);
    }

    public function show(User $user): JsonResponse
    {
        return $this->success(new UserResource($user->load('roles')), 'User details retrieved');
    }

    public function update(UserRequest $request, User $user): JsonResponse
    {
        $data = \Modules\User\DataTransferObjects\UserData::fromRequest($request);
        $user = $this->updateAction->execute($user, $data);
        return $this->success(new UserResource($user), 'User updated successfully');
    }

    public function toggleStatus(User $user): JsonResponse
    {
        $user->update(['active' => !$user->active]);
        return $this->success(new UserResource($user), 'User status updated');
    }

    public function destroy(User $user): JsonResponse
    {
        $this->deleteAction->execute($user);
        return $this->success(null, 'User deleted successfully');
    }
}
