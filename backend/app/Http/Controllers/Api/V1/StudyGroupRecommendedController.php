<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\StudyGroupResource;
use App\Models\StudyGroup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class StudyGroupRecommendedController extends Controller
{
    /**
     * Suggest open groups for the student's courses, ranked by how many members are classmates.
     *
     * This is the rule-based matching (v1); a learned ranker can replace the ordering later.
     */
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $courseIds = $user->courses()->pluck('courses.id');

        $classmates = DB::table('study_group_user as members')
            ->join('course_user as shared', 'shared.user_id', '=', 'members.user_id')
            ->whereColumn('members.study_group_id', 'study_groups.id')
            ->where('members.user_id', '!=', $user->id)
            ->whereIn('shared.course_id', $courseIds)
            ->selectRaw('count(distinct members.user_id)');

        $groups = StudyGroup::query()
            ->with(['course', 'creator'])
            ->withMembership($user)
            ->upcoming()
            ->whereIn('course_id', $courseIds)
            ->whereDoesntHave('members', fn ($members) => $members->whereKey($user->id))
            ->whereRaw('(select count(*) from study_group_user where study_group_id = study_groups.id) < capacity')
            ->orderByDesc($classmates)
            ->orderBy('starts_at')
            ->limit(20)
            ->get();

        return StudyGroupResource::collection($groups);
    }
}
