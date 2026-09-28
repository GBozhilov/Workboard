<?php

namespace App\Policies;

use App\Models\Comment;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Policies\Concerns\ChecksProjectMembership;

class CommentPolicy
{
    use ChecksProjectMembership;

    public function create(User $user, Task $task): bool
    {
        return $this->userCanAccessTaskProject($user, $task);
    }

    public function delete(User $user, Comment $comment): bool
    {
        if ($comment->user_id === $user->id) {
            return true;
        }

        return Project::query()
            ->whereKey($comment->task->project_id)
            ->where('user_id', $user->id)
            ->exists();
    }
}
