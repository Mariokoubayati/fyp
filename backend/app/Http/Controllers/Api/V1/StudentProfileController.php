<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\StudentResource;
use App\Models\User;
use App\Models\Vouch;
use App\Services\Trust\TrustService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentProfileController extends Controller
{
    /**
     * Another student's public profile: no email, plus trust summary and vouch state.
     */
    public function __invoke(Request $request, User $user, TrustService $trust): JsonResponse
    {
        $result = $trust->current($user);

        return response()->json(['data' => [
            ...(new StudentResource($user))->resolve($request),
            'bio' => $user->bio,
            'trust' => ['score' => $result->score, 'tier' => $result->tier, 'max_loan' => $result->maxLoan],
            'vouch_count' => Vouch::where('vouchee_id', $user->id)->count(),
            'vouched_by_me' => Vouch::where('voucher_id', $request->user()->id)->where('vouchee_id', $user->id)->exists(),
        ]]);
    }
}
