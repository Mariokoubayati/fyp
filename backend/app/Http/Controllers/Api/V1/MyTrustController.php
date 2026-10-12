<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Trust\TrustService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MyTrustController extends Controller
{
    /**
     * The signed-in student's own score with its breakdown and recent history.
     */
    public function __invoke(Request $request, TrustService $trust): JsonResponse
    {
        $user = $request->user();
        $result = $trust->refresh($user);

        return response()->json(['data' => [
            'score' => $result->score,
            'tier' => $result->tier,
            'max_loan' => $result->maxLoan,
            'breakdown' => $result->breakdown,
            'history' => $user->trustScoreLogs()
                ->latest('id')
                ->limit(20)
                ->get(['score', 'tier', 'trigger', 'created_at']),
        ]]);
    }
}
