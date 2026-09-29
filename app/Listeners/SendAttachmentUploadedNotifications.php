<?php

namespace App\Listeners;

use App\Events\AttachmentUploaded;
use App\Notifications\AttachmentUploadedNotification;
use App\Support\ProjectNotificationRecipients;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendAttachmentUploadedNotifications implements ShouldQueue
{
    public function handle(AttachmentUploaded $event): void
    {
        $event->attachment->loadMissing('task.project');

        $recipients = ProjectNotificationRecipients::projectOwnerExceptActor(
            $event->attachment->task->project,
            $event->actor,
        );

        foreach ($recipients as $recipient) {
            $recipient->notify(new AttachmentUploadedNotification($event->attachment, $event->actor));
        }
    }
}
