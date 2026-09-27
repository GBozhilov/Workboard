<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;
use App\Policies\Concerns\ChecksProjectOwnership;

class ProjectPolicy
{
    use ChecksProjectOwnership;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Project $project): bool
    {
        return $this->userOwnsProject($user, $project);
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
}
