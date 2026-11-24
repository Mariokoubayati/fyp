<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AuthTokenResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;

class RefreshTokenController extends Controller
{
    /**
     * Swap a valid or recently expired token for a new one. The old token stops working.
     */
    public function __invoke(): AuthTokenResource|JsonResponse
    {
        $guard = Auth::guard('api');

        try {
            $token = $guard->refresh();
        } catch (JWTException) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        return new AuthTokenResource(['token' => $token, 'user' => $guard->setToken($token)->user()]);
    }
}
