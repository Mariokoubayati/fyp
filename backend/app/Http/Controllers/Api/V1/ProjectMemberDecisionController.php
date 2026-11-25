<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ProjectMemberStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ProjectResource;
use App\Models\Project;
use App\Models\User;
use App\Notifications\ProjectRequestAnsweredNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ProjectMemberDecisionController extends Controller
{
    /**
     * Accept or decline a pending join request (owner only). Accepting fails with 409 when the team is full.
     */
    public function __invoke(Request $request, Project $project, User $user): ProjectResource
    {
        Gate::authorize('update', $project);

        $data = $request->validate([
            'status' => ['required', Rule::in([ProjectMemberStatus::Accepted->value, ProjectMemberStatus::Declined->value])],
        ]);
        $status = ProjectMemberStatus::from($data['status']);

        DB::transaction(function () use ($project, $user, $status): void {
            $locked = Project::whereKey($project->id)->lockForUpdate()->firstOrFail();

            abort_unless($locked->requests()->whereKey($user->id)->exists(), 404, 'This student has no pending request.');
            abort_if(
                $status === ProjectMemberStatus::Accepted && $locked->members()->count() >= $locked->team_size,
                409,
                'The team is full. Raise the team size first.',
            );

            $locked->people()->updateExistingPivot($user->id, ['status' => $status->value]);
        });

        $user->notify(new ProjectRequestAnsweredNotification($project->loadMissing('owner'), $status));

        return new ProjectResource($project->load(['course', 'owner', 'members', 'requests'])->loadMembership($request->user()));
    }
}
