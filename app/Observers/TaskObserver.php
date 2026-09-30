<?php

namespace App\Observers;

use App\Models\Task;
use App\Support\ProjectSummaryCache;

class TaskObserver
{
    public function created(Task $task): void
    {
        ProjectSummaryCache::forgetForProjectId($task->project_id);
    }

    public function updated(Task $task): void
    {
        if ($task->wasChanged('status')) {
            ProjectSummaryCache::forgetForProjectId($task->project_id);
        }
    }

    public function deleted(Task $task): void
    {
        ProjectSummaryCache::forgetForProjectId($task->project_id);
    }
}
