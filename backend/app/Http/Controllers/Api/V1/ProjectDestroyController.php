<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ProjectMemberStatus;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Notifications\ProjectDeletedNotification;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;

class ProjectDestroyController extends Controller
{
    /**
     * Delete a project (owner only) and tell its members and anyone waiting on a request.
     */
    public function __invoke(Project $project): Response
    {
        Gate::authorize('delete', $project);

        $owner = $project->owner;
        $people = $project->people()
            ->wherePivotIn('status', [ProjectMemberStatus::Pending->value, ProjectMemberStatus::Accepted->value])
            ->whereKeyNot($owner->id)
            ->get();
        $notification = new ProjectDeletedNotification($project->title, ['id' => $owner->id, 'name' => $owner->name]);

        $project->delete();

        Notification::send($people, $notification);

        return response()->noContent();
    }
}
