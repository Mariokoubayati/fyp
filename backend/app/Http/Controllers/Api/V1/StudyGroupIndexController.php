<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\StudyGroupResource;
use App\Models\StudyGroup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StudyGroupIndexController extends Controller
{
    /**
     * List upcoming study groups, optionally for one course or only the ones the student joined.
     */
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $groups = StudyGroup::query()
            ->with(['course', 'creator'])
            ->withMembership($user)
            ->upcoming()
            ->when($request->integer('course_id'), fn ($query, int $courseId) => $query->where('course_id', $courseId))
            ->when($request->boolean('mine'), fn ($query) => $query->whereHas('members', fn ($members) => $members->whereKey($user->id)))
            ->orderBy('starts_at')
            ->paginate(20)
            ->withQueryString();

        return StudyGroupResource::collection($groups);
    }
}
