<?php
namespace Modules\Auth\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AuthResource extends JsonResource
{

    public function toArray($request): array
    {
        return [
            'token' => $this->resource['token'],
            'token_type' => 'Bearer',
            'user' => new \Modules\User\Http\Resources\UserResource($this->resource['user']),
        ];
    }
}
