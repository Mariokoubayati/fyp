<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\EventResource;
use App\Http\Resources\Api\V1\ListingResource;
use App\Http\Resources\Api\V1\ProjectResource;
use App\Http\Resources\Api\V1\StudyGroupResource;
use App\Services\Recommendations\RecommendationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecommendationController extends Controller
{
    /**
     * The home feed: a few study groups, listings, events and projects picked for the student.
     */
    public function __invoke(Request $request, RecommendationService $recommendations): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'data' => [
                'groups' => StudyGroupResource::collection($recommendations->groups($user, 5)),
                'listings' => ListingResource::collection($recommendations->listings($user, 5)),
                'events' => EventResource::collection($recommendations->events($user, 5)),
                'projects' => ProjectResource::collection($recommendations->projects($user, 3)),
                'source' => $recommendations->usesModel() ? 'model' : 'rules',
            ],
        ]);
    }
}
