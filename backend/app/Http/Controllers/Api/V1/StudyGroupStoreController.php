<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreStudyGroupRequest;
use App\Http\Resources\Api\V1\StudyGroupResource;
use App\Models\StudyGroup;
use Illuminate\Http\JsonResponse;

class StudyGroupStoreController extends Controller
{
    /**
     * Start a study group; the creator joins it automatically.
     */
    public function __invoke(StoreStudyGroupRequest $request): JsonResponse
    {
        $user = $request->user();

        $group = new StudyGroup($request->validated());
        $group->creator()->associate($user);
        $group->save();
        $group->members()->attach($user);

        return (new StudyGroupResource($group->load(['course', 'creator'])->loadMembership($user)))
            ->response()
            ->setStatusCode(201);
    }
}
