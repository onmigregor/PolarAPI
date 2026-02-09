<?php

namespace Modules\User\DataTransferObjects;

use Illuminate\Http\Request;

readonly class UserData
{
    public function __construct(
        public string $name,
        public string $email,
        public ?string $password = null,
        public array $roles = [],
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            name: $request->input('name'),
            email: $request->input('email'),
            password: $request->input('password'),
            roles: $request->input('roles', []),
        );
    }
}
