<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Resources\Api\V1\AuthTokenResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    /**
     * Exchange credentials for a JWT.
     */
    public function __invoke(LoginRequest $request): AuthTokenResource|JsonResponse
    {
        $guard = Auth::guard('api');

        $token = $guard->attempt($request->validated());

        if (! $token) {
            return response()->json(['message' => 'Invalid email or password.'], 401);
        }

        return new AuthTokenResource(['token' => $token, 'user' => $guard->user()]);
    }
}
