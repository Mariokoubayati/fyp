<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\StudyGroup;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class StudyGroupDestroyController extends Controller
{
    /**
     * Cancel a study group (creator only).
     */
    public function __invoke(StudyGroup $studyGroup): Response
    {
        Gate::authorize('delete', $studyGroup);

        $studyGroup->delete();

        return response()->noContent();
    }
}
