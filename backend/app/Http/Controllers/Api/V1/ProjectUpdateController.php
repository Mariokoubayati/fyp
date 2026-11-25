<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateProjectRequest;
use App\Http\Resources\Api\V1\ProjectResource;
use App\Models\Project;
use Illuminate\Support\Facades\Gate;

class ProjectUpdateController extends Controller
{
    /**
     * Edit a project, or close it to new members (owner only).
     */
    public function __invoke(UpdateProjectRequest $request, Project $project): ProjectResource
    {
        Gate::authorize('update', $project);

        $project->update($request->project());

        return new ProjectResource($project->load(['course', 'owner', 'members', 'requests'])->loadMembership($request->user()));
    }
}
