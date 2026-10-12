<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RegisterRequest;
use App\Http\Resources\Api\V1\AuthTokenResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class RegisterController extends Controller
{
    /**
     * Create an account and sign the new user in.
     */
    public function __invoke(RegisterRequest $request): JsonResponse
    {
        $user = User::create($request->validated());

        $token = Auth::guard('api')->login($user);

        return (new AuthTokenResource(['token' => $token, 'user' => $user]))
            ->response()
            ->setStatusCode(201);
    }
}
