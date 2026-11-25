<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ProjectMemberStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ProjectResource;
use App\Models\Project;
use App\Notifications\ProjectJoinRequestedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProjectJoinRequestController extends Controller
{
    /**
     * Ask to join a project (idempotent). The owner is notified and decides.
     * Fails with 409 when the project is closed or full, or the owner already said no.
     */
    public function __invoke(Request $request, Project $project): ProjectResource
    {
        $data = $request->validate(['message' => ['nullable', 'string', 'max:300']]);
        $user = $request->user();

        abort_if($user->is($project->owner), 422, 'This is your project.');

        $created = DB::transaction(function () use ($project, $user, $data): bool {
            $locked = Project::whereKey($project->id)->lockForUpdate()->firstOrFail();
            $status = $locked->people()->whereKey($user->id)->first()?->pivot->status;

            if ($status === ProjectMemberStatus::Declined->value) {
                abort(409, 'The owner already declined your request.');
            }

            if ($status !== null) {
                return false;
            }

            abort_unless($locked->is_open, 409, 'This project is not looking for members.');
            abort_if($locked->members()->count() >= $locked->team_size, 409, 'This team is full.');

            $locked->people()->attach($user, [
                'status' => ProjectMemberStatus::Pending->value,
                'message' => $data['message'] ?? null,
            ]);

            return true;
        });

        if ($created) {
            $project->owner->notify(new ProjectJoinRequestedNotification($project, $user));
        }

        return new ProjectResource($project->load(['course', 'owner', 'members', 'requests'])->loadMembership($user));
    }
}
