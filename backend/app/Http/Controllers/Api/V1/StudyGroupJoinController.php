<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TrustEventType;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\StudyGroupResource;
use App\Models\StudyGroup;
use App\Services\Trust\TrustService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StudyGroupJoinController extends Controller
{
    /**
     * Join a study group (idempotent). Fails with 409 when the group is full or has started.
     */
    public function __invoke(Request $request, StudyGroup $studyGroup, TrustService $trust): StudyGroupResource
    {
        $user = $request->user();

        DB::transaction(function () use ($studyGroup, $user): void {
            $group = StudyGroup::whereKey($studyGroup->id)->lockForUpdate()->firstOrFail();

            if ($group->members()->whereKey($user->id)->exists()) {
                return;
            }

            abort_if($group->starts_at->isPast(), 409, 'This study group has already started.');
            abort_if($group->members()->count() >= $group->capacity, 409, 'This study group is full.');

            $group->members()->attach($user);
        });

        $trust->record($user, TrustEventType::GroupJoined, $studyGroup);

        return new StudyGroupResource($studyGroup->load(['course', 'creator'])->loadMembership($user));
    }
}
