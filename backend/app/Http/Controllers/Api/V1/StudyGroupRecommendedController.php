<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\StudyGroupResource;
use App\Services\Recommendations\RecommendationService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StudyGroupRecommendedController extends Controller
{
    /**
     * Suggest open groups for the student: ranked by the recommender when it is enabled,
     * otherwise groups in the student's courses ordered by number of classmates.
     */
    public function __invoke(Request $request, RecommendationService $recommendations): AnonymousResourceCollection
    {
        return StudyGroupResource::collection($recommendations->groups($request->user()));
    }
}
