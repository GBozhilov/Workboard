<?php

namespace App\Notifications;

use App\Models\Project;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ProjectMemberAddedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Project $project,
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
        return [
            'type' => 'project_member_added',
            'project_id' => $this->project->id,
            'project_name' => $this->project->name,
            'actor_id' => $this->actor->id,
            'actor_name' => $this->actor->name,
            'message' => sprintf(
                '%s added you to project "%s".',
                $this->actor->name,
                $this->project->name,
            ),
        ];
    }
}
