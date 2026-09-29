<?php

namespace App\Notifications;

use App\Models\Task;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class TaskCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Task $task,
        public User $actor,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $project = $this->task->project;

        return [
            'type' => 'task_created',
            'project_id' => $project->id,
            'project_name' => $project->name,
            'task_id' => $this->task->id,
            'task_title' => $this->task->title,
            'actor_id' => $this->actor->id,
            'actor_name' => $this->actor->name,
            'message' => sprintf(
                '%s created task "%s" in %s.',
                $this->actor->name,
                $this->task->title,
                $project->name,
            ),
        ];
    }
}
