<?php

namespace App\Notifications;

use App\Models\TaskAttachment;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class AttachmentUploadedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public TaskAttachment $attachment,
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
        $task = $this->attachment->task;
        $project = $task->project;

        return [
            'type' => 'attachment_uploaded',
            'project_id' => $project->id,
            'project_name' => $project->name,
            'task_id' => $task->id,
            'task_title' => $task->title,
            'attachment_id' => $this->attachment->id,
            'actor_id' => $this->actor->id,
            'actor_name' => $this->actor->name,
            'message' => sprintf(
                '%s uploaded "%s" to task "%s".',
                $this->actor->name,
                $this->attachment->original_name,
                $task->title,
            ),
        ];
    }
}
