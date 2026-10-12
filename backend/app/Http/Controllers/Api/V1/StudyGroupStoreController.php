<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TrustEventType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreStudyGroupRequest;
use App\Http\Resources\Api\V1\StudyGroupResource;
use App\Models\StudyGroup;
use App\Services\Trust\TrustService;
use Illuminate\Http\JsonResponse;

class StudyGroupStoreController extends Controller
{
    /**
     * Start a study group; the creator joins it automatically.
     */
    public function __invoke(StoreStudyGroupRequest $request, TrustService $trust): JsonResponse
    {
        $user = $request->user();

        $group = new StudyGroup($request->validated());
        $group->creator()->associate($user);
        $group->save();
        $group->members()->attach($user);
        $trust->record($user, TrustEventType::GroupJoined, $group);

        return (new StudyGroupResource($group->load(['course', 'creator'])->loadMembership($user)))
            ->response()
            ->setStatusCode(201);
    }
}
