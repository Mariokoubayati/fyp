<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ProjectMemberStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreProjectRequest;
use App\Http\Resources\Api\V1\ProjectResource;
use App\Models\Project;
use Illuminate\Http\JsonResponse;

class ProjectStoreController extends Controller
{
    /**
     * Post a project looking for members; the owner is the first team member.
     */
    public function __invoke(StoreProjectRequest $request): JsonResponse
    {
        $user = $request->user();

        $project = new Project($request->project());
        $project->owner()->associate($user);
        $project->save();
        $project->people()->attach($user, ['status' => ProjectMemberStatus::Accepted->value]);

        return (new ProjectResource($project->load(['course', 'owner', 'members', 'requests'])->loadMembership($user)))
            ->response()
            ->setStatusCode(201);
    }
}
