<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    /**
     * Only the owner can edit the project or answer join requests.
     */
    public function update(User $user, Project $project): bool
    {
        return $user->is($project->owner);
    }

    /**
     * Only the owner can delete the project.
     */
    public function delete(User $user, Project $project): bool
    {
        return $user->is($project->owner);
    }
}
