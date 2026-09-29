<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\User;
use App\Policies\Concerns\ChecksProjectMembership;

class TaskAttachmentPolicy
{
    use ChecksProjectMembership;

    public function create(User $user, Task $task): bool
    {
        return $this->userCanAccessTaskProject($user, $task);
    }

    public function download(User $user, TaskAttachment $attachment): bool
    {
        $attachment->loadMissing('task');

        return $this->userCanAccessTaskProject($user, $attachment->task);
    }

    public function preview(User $user, TaskAttachment $attachment): bool
    {
        return $this->download($user, $attachment);
    }

    public function delete(User $user, TaskAttachment $attachment): bool
    {
        $attachment->loadMissing('task');

        if (! $this->userCanAccessTaskProject($user, $attachment->task)) {
            return false;
        }

        if ($attachment->user_id === $user->id) {
            return true;
        }

        return Project::query()
            ->whereKey($attachment->task->project_id)
            ->where('user_id', $user->id)
            ->exists();
    }
}
