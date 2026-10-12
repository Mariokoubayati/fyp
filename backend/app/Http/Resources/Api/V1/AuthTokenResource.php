<?php

namespace App\Http\Resources\Api\V1;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Wraps an issued JWT together with the user it belongs to.
 *
 * @property array{token: string, user: User} $resource
 */
class AuthTokenResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'access_token' => $this->resource['token'],
            'token_type' => 'bearer',
            'expires_in' => config('jwt.ttl') * 60,
            'user' => new UserResource($this->resource['user']),
        ];
    }
}
