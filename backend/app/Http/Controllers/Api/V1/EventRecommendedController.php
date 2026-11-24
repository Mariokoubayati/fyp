<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\EventResource;
use App\Services\Recommendations\RecommendationService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EventRecommendedController extends Controller
{
    /**
     * Suggest upcoming events for the student: ranked by the recommender when it is enabled,
     * otherwise matching interests first, then the student's courses, then the soonest.
     */
    public function __invoke(Request $request, RecommendationService $recommendations): AnonymousResourceCollection
    {
        return EventResource::collection($recommendations->events($request->user()));
    }
}
