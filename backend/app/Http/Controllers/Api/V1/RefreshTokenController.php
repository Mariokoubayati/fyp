<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AuthTokenResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RefreshTokenController extends Controller
{
    /**
     * Swap the current token for a fresh one and invalidate the old one.
     */
    public function __invoke(Request $request): AuthTokenResource
    {
        $user = $request->user();

        $token = Auth::guard('api')->refresh();

        return new AuthTokenResource(['token' => $token, 'user' => $user]);
    }
}
