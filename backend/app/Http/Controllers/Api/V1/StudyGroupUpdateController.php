<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateStudyGroupRequest;
use App\Http\Resources\Api\V1\StudyGroupResource;
use App\Models\StudyGroup;
use Illuminate\Support\Facades\Gate;

class StudyGroupUpdateController extends Controller
{
    /**
     * Edit a study group (creator only).
     */
    public function __invoke(UpdateStudyGroupRequest $request, StudyGroup $studyGroup): StudyGroupResource
    {
        Gate::authorize('update', $studyGroup);

        $studyGroup->update($request->validated());

        return new StudyGroupResource($studyGroup->load(['course', 'creator'])->loadMembership($request->user()));
    }
}
