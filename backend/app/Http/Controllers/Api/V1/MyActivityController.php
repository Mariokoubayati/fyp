<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Activity\ActivitySummary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MyActivityController extends Controller
{
    /**
     * The signed-in student's own dashboard: activity totals, the last four months and badges.
     */
    public function __invoke(Request $request, ActivitySummary $summary): JsonResponse
    {
        return response()->json(['data' => $summary->for($request->user())]);
    }
}
