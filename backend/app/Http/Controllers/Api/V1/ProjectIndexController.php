<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\CampusTag;
use App\Enums\ProjectMemberStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ProjectResource;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProjectIndexController extends Controller
{
    /**
     * List projects: open ones looking for members (default), the student's own, or the teams they joined.
     * Optionally only one tag, or a title search.
     */
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'filter' => ['nullable', 'in:all,mine,joined'],
            'tag' => ['nullable', 'string'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $user = $request->user();
        $filter = $request->query('filter', 'all');
        $tag = CampusTag::tryFrom((string) $request->query('tag'));

        $projects = Project::query()
            ->with(['course', 'owner'])
            ->withMembership($user)
            ->when($filter === 'all', fn ($query) => $query->where('is_open', true))
            ->when($filter === 'mine', fn ($query) => $query->where('owner_id', $user->id))
            ->when($filter === 'joined', fn ($query) => $query
                ->where('owner_id', '!=', $user->id)
                ->whereHas('people', fn ($people) => $people->whereKey($user->id)->where('project_user.status', ProjectMemberStatus::Accepted->value)))
            ->when($tag, fn ($query) => $query->whereJsonContains('tags', $tag->value))
            ->when($request->filled('search'), fn ($query) => $query->whereContains('title', (string) $request->query('search')))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return ProjectResource::collection($projects);
    }
}
