<?php

namespace App\Support;

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;

class WorkspaceOverviewQuery
{
    public function forUser(User $user): WorkspaceOverview
    {
        $accessibleProjectIds = Project::query()
            ->accessibleBy($user)
            ->pluck('id');

        $openTasksQuery = Task::query()
            ->whereIn('project_id', $accessibleProjectIds)
            ->whereIn('status', [TaskStatus::Todo, TaskStatus::InProgress]);

        return new WorkspaceOverview(
            accessibleProjectsCount: $accessibleProjectIds->count(),
            openTasksCount: (clone $openTasksQuery)->count(),
            unreadNotificationsCount: $user->unreadNotifications()->count(),
            assignedToMeOpenTasksCount: (clone $openTasksQuery)
                ->where('assigned_to', $user->id)
                ->count(),
        );
    }
}
