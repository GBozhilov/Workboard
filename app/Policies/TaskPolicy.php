<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Policies\Concerns\ChecksProjectOwnership;

class TaskPolicy
{
    use ChecksProjectOwnership;

    public function viewAny(User $user, Project $project): bool
    {
        return $this->userOwnsProject($user, $project);
    }

    public function view(User $user, Task $task): bool
    {
        return $this->userOwnsTaskThroughProject($user, $task);
    }

    public function create(User $user, Project $project): bool
    {
        return $this->userOwnsProject($user, $project);
    }

    public function update(User $user, Task $task): bool
    {
        return $this->userOwnsTaskThroughProject($user, $task);
    }

    public function delete(User $user, Task $task): bool
    {
        return $this->userOwnsTaskThroughProject($user, $task);
    }
}
