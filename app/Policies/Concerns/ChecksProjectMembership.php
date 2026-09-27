<?php

namespace App\Policies\Concerns;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;

trait ChecksProjectMembership
{
    protected function userOwnsProject(User $user, Project $project): bool
    {
        return $user->id === $project->user_id;
    }

    protected function userBelongsToProject(User $user, Project $project): bool
    {
        if ($this->userOwnsProject($user, $project)) {
            return true;
        }

        return $project->members()->whereKey($user->id)->exists();
    }

    protected function userCanAccessTaskProject(User $user, Task $task): bool
    {
        return Project::query()
            ->whereKey($task->project_id)
            ->where(function ($query) use ($user): void {
                $query->where('user_id', $user->id)
                    ->orWhereHas('members', fn ($members) => $members->whereKey($user->id));
            })
            ->exists();
    }
}
