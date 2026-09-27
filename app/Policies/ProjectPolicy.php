<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;
use App\Policies\Concerns\ChecksProjectMembership;

class ProjectPolicy
{
    use ChecksProjectMembership;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Project $project): bool
    {
        return $this->userBelongsToProject($user, $project);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Project $project): bool
    {
        return $this->userOwnsProject($user, $project);
    }

    public function delete(User $user, Project $project): bool
    {
        return $this->userOwnsProject($user, $project);
    }

    public function manageMembers(User $user, Project $project): bool
    {
        return $this->userOwnsProject($user, $project);
    }
}
