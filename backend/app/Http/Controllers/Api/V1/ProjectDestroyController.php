<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ProjectDestroyController extends Controller
{
    /**
     * Delete a project (owner only).
     */
    public function __invoke(Project $project): Response
    {
        Gate::authorize('delete', $project);

        $project->delete();

        return response()->noContent();
    }
}
