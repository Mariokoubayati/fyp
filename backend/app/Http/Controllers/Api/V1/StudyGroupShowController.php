<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\StudyGroupResource;
use App\Models\StudyGroup;
use Illuminate\Http\Request;

class StudyGroupShowController extends Controller
{
    /**
     * Show one study group.
     */
    public function __invoke(Request $request, StudyGroup $studyGroup): StudyGroupResource
    {
        return new StudyGroupResource($studyGroup->load(['course', 'creator'])->loadMembership($request->user()));
    }
}
