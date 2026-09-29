<?php

namespace App\Listeners;

use App\Events\TaskCreated;
use App\Notifications\TaskCreatedNotification;
use App\Support\ProjectNotificationRecipients;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendTaskCreatedNotifications implements ShouldQueue
{
    public function handle(TaskCreated $event): void
    {
        $event->task->loadMissing('project');

        $recipients = ProjectNotificationRecipients::projectOwnerExceptActor(
            $event->task->project,
            $event->actor,
        );

        foreach ($recipients as $recipient) {
            $recipient->notify(new TaskCreatedNotification($event->task, $event->actor));
        }
    }
}
