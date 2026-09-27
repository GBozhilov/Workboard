<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Policies\Concerns\ChecksProjectMembership;

class TaskPolicy
{
    use ChecksProjectMembership;

    public function viewAny(User $user, Project $project): bool
    {
        return $this->userBelongsToProject($user, $project);
    }

    public function view(User $user, Task $task): bool
    {
        return $this->userCanAccessTaskProject($user, $task);
    }

    public function create(User $user, Project $project): bool
    {
        return $this->userBelongsToProject($user, $project);
    }

    public function update(User $user, Task $task): bool
    {
        return $this->userCanAccessTaskProject($user, $task);
    }

    public function delete(User $user, Task $task): bool
    {
        return Project::query()
            ->whereKey($task->project_id)
            ->where('user_id', $user->id)
            ->exists();
    }
}
