<?php

namespace App\Policies\Concerns;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;

trait ChecksProjectOwnership
{
    protected function userOwnsProject(User $user, Project $project): bool
    {
        return $user->id === $project->user_id;
    }

    protected function userOwnsTaskThroughProject(User $user, Task $task): bool
    {
        return Project::query()
            ->whereKey($task->project_id)
            ->where('user_id', $user->id)
            ->exists();
    }
}
