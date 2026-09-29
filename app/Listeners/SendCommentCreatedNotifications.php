<?php

namespace App\Listeners;

use App\Events\CommentCreated;
use App\Notifications\CommentCreatedNotification;
use App\Support\ProjectNotificationRecipients;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendCommentCreatedNotifications implements ShouldQueue
{
    public function handle(CommentCreated $event): void
    {
        $event->comment->loadMissing('task.project');

        $recipients = ProjectNotificationRecipients::projectOwnerExceptActor(
            $event->comment->task->project,
            $event->actor,
        );

        foreach ($recipients as $recipient) {
            $recipient->notify(new CommentCreatedNotification($event->comment, $event->actor));
        }
    }
}
