<?php

namespace App\Policies;

use App\Models\StudyGroup;
use App\Models\User;

class StudyGroupPolicy
{
    /**
     * Only the student who created the group can edit it.
     */
    public function update(User $user, StudyGroup $studyGroup): bool
    {
        return $user->is($studyGroup->creator);
    }

    /**
     * Only the student who created the group can delete it.
     */
    public function delete(User $user, StudyGroup $studyGroup): bool
    {
        return $user->is($studyGroup->creator);
    }
}
