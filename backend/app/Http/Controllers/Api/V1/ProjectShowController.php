<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ProjectResource;
use App\Models\Project;
use Illuminate\Http\Request;

class ProjectShowController extends Controller
{
    /**
     * Show one project with its team (and, for the owner, the pending requests).
     */
    public function __invoke(Request $request, Project $project): ProjectResource
    {
        return new ProjectResource($project->load(['course', 'owner', 'members', 'requests'])->loadMembership($request->user()));
    }
}
