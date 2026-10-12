<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\StudyGroup;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class StudyGroupLeaveController extends Controller
{
    /**
     * Leave a study group (idempotent). The creator must delete the group instead.
     */
    public function __invoke(Request $request, StudyGroup $studyGroup): Response
    {
        $user = $request->user();

        abort_if($user->is($studyGroup->creator), 422, 'You created this group. Delete it instead of leaving.');

        $studyGroup->members()->detach($user->id);

        return response()->noContent();
    }
}
