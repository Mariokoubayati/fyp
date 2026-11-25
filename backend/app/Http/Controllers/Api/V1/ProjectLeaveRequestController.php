<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ProjectMemberStatus;
use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ProjectLeaveRequestController extends Controller
{
    /**
     * Withdraw a pending request or leave the team (idempotent). A declined request stays on record
     * so the student cannot keep asking. The owner deletes the project instead.
     */
    public function __invoke(Request $request, Project $project): Response
    {
        $user = $request->user();

        abort_if($user->is($project->owner), 422, 'You own this project. Delete it instead of leaving.');

        $project->people()
            ->wherePivotIn('status', [ProjectMemberStatus::Pending->value, ProjectMemberStatus::Accepted->value])
            ->detach($user->id);

        return response()->noContent();
    }
}
