<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResendVerificationController extends Controller
{
    /**
     * Send the verification email again.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        return response()->json(['message' => 'Verification link sent.'], 202);
    }
}
