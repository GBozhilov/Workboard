<?php

namespace App\Listeners;

use App\Events\ProjectMemberAdded;
use App\Notifications\ProjectMemberAddedNotification;
use App\Support\ProjectNotificationRecipients;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendProjectMemberAddedNotifications implements ShouldQueue
{
    public function handle(ProjectMemberAdded $event): void
    {
        $recipients = ProjectNotificationRecipients::addedProjectMember(
            $event->member,
            $event->actor,
        );

        foreach ($recipients as $recipient) {
            $recipient->notify(new ProjectMemberAddedNotification($event->project, $event->actor));
        }
    }
}
