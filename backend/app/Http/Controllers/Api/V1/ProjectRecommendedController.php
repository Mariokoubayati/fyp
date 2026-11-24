<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ProjectResource;
use App\Services\Recommendations\RecommendationService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProjectRecommendedController extends Controller
{
    /**
     * Suggest open projects for the student: ranked by the recommender when it is enabled,
     * otherwise the ones that need the student's skills first.
     */
    public function __invoke(Request $request, RecommendationService $recommendations): AnonymousResourceCollection
    {
        return ProjectResource::collection($recommendations->projects($request->user()));
    }
}
