<?php

namespace Tests\Feature;

use App\Enums\ProjectRole;
use App\Events\AttachmentUploaded;
use App\Events\CommentCreated;
use App\Events\ProjectMemberAdded;
use App\Events\TaskCreated;
use App\Listeners\SendAttachmentUploadedNotifications;
use App\Listeners\SendCommentCreatedNotifications;
use App\Listeners\SendProjectMemberAddedNotifications;
use App\Listeners\SendTaskCreatedNotifications;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Notifications\AttachmentUploadedNotification;
use App\Notifications\CommentCreatedNotification;
use App\Notifications\ProjectMemberAddedNotification;
use App\Notifications\TaskCreatedNotification;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use App\Models\TaskAttachment;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesWorkBoardFixtures;
use Tests\TestCase;

class WorkBoardEventsAndNotificationsTest extends TestCase
{
    use CreatesWorkBoardFixtures;
    use RefreshDatabase;

    public function test_task_created_event_dispatched_after_valid_task_store(): void
    {
        Event::fake([TaskCreated::class]);
        ['owner' => $owner, 'project' => $project] = $this->ownedProject();

        $this->actingAs($owner)->post(route('projects.tasks.store', $project), [
            'title' => 'Event task',
            'description' => null,
            'status' => 'todo',
            'priority' => 'medium',
            'due_date' => null,
        ])->assertRedirect();

        Event::assertDispatched(TaskCreated::class, function (TaskCreated $event) {
            return $event->task->title === 'Event task';
        });
    }

    public function test_invalid_task_store_does_not_dispatch_task_created_event(): void
    {
        Event::fake([TaskCreated::class]);
        ['owner' => $owner, 'project' => $project] = $this->ownedProject();

        $this->actingAs($owner)
            ->from(route('projects.tasks.create', $project))
            ->post(route('projects.tasks.store', $project), ['title' => ''])
            ->assertSessionHasErrors('title');

        Event::assertNotDispatched(TaskCreated::class);
    }

    public function test_comment_created_event_dispatched_after_comment_store(): void
    {
        Event::fake([CommentCreated::class]);
        ['owner' => $owner, 'project' => $project, 'task' => $task] = $this->ownedTask();

        $this->actingAs($owner)->post(route('projects.tasks.comments.store', [$project, $task]), [
            'body' => 'Event comment',
        ])->assertRedirect();

        Event::assertDispatched(CommentCreated::class);
    }

    public function test_project_member_added_event_dispatched_after_member_store(): void
    {
        Event::fake([ProjectMemberAdded::class]);
        ['owner' => $owner, 'project' => $project] = $this->ownedProject();
        $member = User::factory()->create();

        $this->actingAs($owner)->post(route('projects.members.store', $project), [
            'email' => $member->email,
            'role' => 'member',
        ])->assertRedirect();

        Event::assertDispatched(ProjectMemberAdded::class);
    }

    public function test_attachment_uploaded_event_dispatched_after_attachment_store(): void
    {
        Event::fake([AttachmentUploaded::class]);
        Storage::fake(TaskAttachment::STORAGE_DISK);
        ['owner' => $owner, 'project' => $project, 'task' => $task] = $this->ownedTask();

        $this->actingAs($owner)->post(route('projects.tasks.attachments.store', [$project, $task]), [
            'file' => UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf'),
        ])->assertRedirect();

        Event::assertDispatched(AttachmentUploaded::class);
    }

    public function test_task_created_listener_is_queued(): void
    {
        Queue::fake();
        ['member' => $member, 'project' => $project] = $this->sharedProject();

        $this->actingAs($member)->post(route('projects.tasks.store', $project), [
            'title' => 'Queued notify task',
            'description' => null,
            'status' => 'todo',
            'priority' => 'medium',
            'due_date' => null,
        ])->assertRedirect();

        Queue::assertPushed(CallQueuedListener::class, 1);
        Queue::assertPushed(CallQueuedListener::class, function (CallQueuedListener $job): bool {
            return $job->class === SendTaskCreatedNotifications::class;
        });
    }

    public function test_workboard_event_listeners_are_registered_only_once(): void
    {
        $dispatcher = $this->app->make(Dispatcher::class);

        $this->assertCount(1, $dispatcher->getListeners(TaskCreated::class));
        $this->assertCount(1, $dispatcher->getListeners(CommentCreated::class));
        $this->assertCount(1, $dispatcher->getListeners(ProjectMemberAdded::class));
        $this->assertCount(1, $dispatcher->getListeners(AttachmentUploaded::class));
    }

    public function test_single_comment_creates_exactly_one_database_notification_for_owner(): void
    {
        ['owner' => $owner, 'member' => $member, 'project' => $project] = $this->sharedProject();
        $task = Task::factory()->for($project)->create(['title' => 'Task 2']);

        $this->actingAs($member)->post(route('projects.tasks.comments.store', [$project, $task]), [
            'body' => 'One comment only',
        ])->assertRedirect();

        $this->assertSame(1, $owner->fresh()->notifications()->count());
        $this->assertSame('comment_created', $owner->fresh()->notifications()->first()->data['type']);
    }

    public function test_single_task_creates_exactly_one_database_notification_for_owner(): void
    {
        ['owner' => $owner, 'member' => $member, 'project' => $project] = $this->sharedProject();

        $this->actingAs($member)->post(route('projects.tasks.store', $project), [
            'title' => 'Single notify task',
            'description' => null,
            'status' => 'todo',
            'priority' => 'medium',
            'due_date' => null,
        ])->assertRedirect();

        $this->assertSame(1, $owner->fresh()->notifications()->count());
    }

    public function test_project_member_added_creates_exactly_one_database_notification(): void
    {
        ['owner' => $owner, 'project' => $project] = $this->ownedProject();
        $member = User::factory()->create();

        $this->actingAs($owner)->post(route('projects.members.store', $project), [
            'email' => $member->email,
            'role' => 'member',
        ])->assertRedirect();

        $this->assertSame(1, $member->fresh()->notifications()->count());
    }

    public function test_attachment_upload_creates_exactly_one_database_notification_for_owner(): void
    {
        Storage::fake(TaskAttachment::STORAGE_DISK);
        ['owner' => $owner, 'member' => $member, 'project' => $project] = $this->sharedProject();
        $task = Task::factory()->for($project)->create();

        $this->actingAs($member)->post(route('projects.tasks.attachments.store', [$project, $task]), [
            'file' => UploadedFile::fake()->create('once.pdf', 5, 'application/pdf'),
        ])->assertRedirect();

        $this->assertSame(1, $owner->fresh()->notifications()->count());
    }

    public function test_project_owner_who_is_also_listed_as_member_receives_one_task_notification(): void
    {
        ['owner' => $owner, 'member' => $member, 'project' => $project] = $this->sharedProject();
        $project->members()->syncWithoutDetaching([
            $owner->id => ['role' => ProjectRole::Owner->value],
        ]);

        $this->actingAs($member)->post(route('projects.tasks.store', $project), [
            'title' => 'No duplicate owner',
            'description' => null,
            'status' => 'todo',
            'priority' => 'medium',
            'due_date' => null,
        ])->assertRedirect();

        $this->assertSame(1, $owner->fresh()->notifications()->count());
    }

    public function test_member_creating_task_notifies_owner_not_actor(): void
    {
        Notification::fake();
        ['owner' => $owner, 'member' => $member, 'project' => $project] = $this->sharedProject();

        $this->actingAs($member)->post(route('projects.tasks.store', $project), [
            'title' => 'Notify owner',
            'description' => null,
            'status' => 'todo',
            'priority' => 'medium',
            'due_date' => null,
        ])->assertRedirect();

        Notification::assertSentTo($owner, TaskCreatedNotification::class);
        Notification::assertNotSentTo($member, TaskCreatedNotification::class);
    }

    public function test_owner_creating_task_does_not_notify_themselves(): void
    {
        Notification::fake();
        ['owner' => $owner, 'project' => $project] = $this->ownedProject();

        $this->actingAs($owner)->post(route('projects.tasks.store', $project), [
            'title' => 'Owner task',
            'description' => null,
            'status' => 'todo',
            'priority' => 'medium',
            'due_date' => null,
        ])->assertRedirect();

        Notification::assertNothingSent();
    }

    public function test_comment_notifies_owner_but_not_comment_author(): void
    {
        Notification::fake();
        ['owner' => $owner, 'member' => $member, 'project' => $project] = $this->sharedProject();
        $task = Task::factory()->for($project)->create();

        $this->actingAs($member)->post(route('projects.tasks.comments.store', [$project, $task]), [
            'body' => 'Member comment',
        ])->assertRedirect();

        Notification::assertSentTo($owner, CommentCreatedNotification::class);
        Notification::assertNotSentTo($member, CommentCreatedNotification::class);
    }

    public function test_added_project_member_receives_notification(): void
    {
        Notification::fake();
        ['owner' => $owner, 'project' => $project] = $this->ownedProject();
        $member = User::factory()->create();

        $this->actingAs($owner)->post(route('projects.members.store', $project), [
            'email' => $member->email,
            'role' => 'member',
        ])->assertRedirect();

        Notification::assertSentTo($member, ProjectMemberAddedNotification::class);
        Notification::assertNotSentTo($owner, ProjectMemberAddedNotification::class);
    }

    public function test_attachment_upload_notifies_owner_when_member_uploads(): void
    {
        Notification::fake();
        Storage::fake(TaskAttachment::STORAGE_DISK);
        ['owner' => $owner, 'member' => $member, 'project' => $project] = $this->sharedProject();
        $task = Task::factory()->for($project)->create();

        $this->actingAs($member)->post(route('projects.tasks.attachments.store', [$project, $task]), [
            'file' => UploadedFile::fake()->create('file.pdf', 5, 'application/pdf'),
        ])->assertRedirect();

        Notification::assertSentTo($owner, AttachmentUploadedNotification::class);
        Notification::assertNotSentTo($member, AttachmentUploadedNotification::class);
    }

    public function test_database_notification_stored_and_marked_read(): void
    {
        ['owner' => $owner, 'member' => $member, 'project' => $project] = $this->sharedProject();

        $this->actingAs($member)->post(route('projects.tasks.store', $project), [
            'title' => 'DB notify',
            'description' => null,
            'status' => 'todo',
            'priority' => 'medium',
            'due_date' => null,
        ])->assertRedirect();

        $this->assertSame(1, $owner->fresh()->notifications()->count());

        $notification = $owner->fresh()->notifications()->first();
        $this->assertNotNull($notification);
        $this->assertNull($notification->read_at);
        $this->assertSame('task_created', $notification->data['type']);
        $this->assertSame('DB notify', $notification->data['task_title']);

        $this->actingAs($owner)
            ->post(route('notifications.read', $notification->id))
            ->assertRedirect();

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_mark_all_as_read_marks_only_authenticated_user_notifications(): void
    {
        ['owner' => $owner, 'member' => $member, 'project' => $project] = $this->sharedProject();
        $other = User::factory()->create();

        $this->actingAs($member)->post(route('projects.tasks.store', $project), [
            'title' => 'One',
            'description' => null,
            'status' => 'todo',
            'priority' => 'medium',
            'due_date' => null,
        ]);
        $this->actingAs($member)->post(route('projects.tasks.store', $project), [
            'title' => 'Two',
            'description' => null,
            'status' => 'todo',
            'priority' => 'medium',
            'due_date' => null,
        ]);

        $this->actingAs($owner)->post(route('notifications.read-all'))->assertRedirect();

        $this->assertSame(0, $owner->fresh()->unreadNotifications()->count());
        $this->assertSame(0, $other->fresh()->unreadNotifications()->count());
    }

    public function test_user_cannot_mark_another_users_notification_as_read(): void
    {
        ['owner' => $owner, 'member' => $member, 'project' => $project] = $this->sharedProject();

        $this->actingAs($member)->post(route('projects.tasks.store', $project), [
            'title' => 'Private note',
            'description' => null,
            'status' => 'todo',
            'priority' => 'medium',
            'due_date' => null,
        ]);

        $notification = $owner->fresh()->notifications()->firstOrFail();

        $this->actingAs($member)
            ->post(route('notifications.read', $notification->id))
            ->assertNotFound();
    }

    public function test_notifications_index_lists_only_authenticated_users_notifications(): void
    {
        ['owner' => $owner, 'member' => $member, 'project' => $project] = $this->sharedProject();
        $outsider = User::factory()->create();

        $this->actingAs($member)->post(route('projects.tasks.store', $project), [
            'title' => 'Listed task',
            'description' => null,
            'status' => 'todo',
            'priority' => 'medium',
            'due_date' => null,
        ]);

        $this->actingAs($owner)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Listed task', false);

        $this->actingAs($outsider)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertDontSee('Listed task', false);
    }

    public function test_notification_open_resolves_to_task_show_route(): void
    {
        ['owner' => $owner, 'member' => $member, 'project' => $project] = $this->sharedProject();
        $task = Task::factory()->for($project)->create(['title' => 'Link task']);

        $this->actingAs($member)->post(route('projects.tasks.comments.store', [$project, $task]), [
            'body' => 'Link comment',
        ]);

        $notification = $owner->fresh()->notifications()->firstOrFail();

        $this->actingAs($owner)
            ->get(route('notifications.open', $notification))
            ->assertRedirect(route('projects.tasks.show', [$project, $task]).'#comments');
    }

    public function test_notifications_page_shows_unread_badge_in_layout(): void
    {
        ['owner' => $owner, 'member' => $member, 'project' => $project] = $this->sharedProject();

        $this->actingAs($member)->post(route('projects.tasks.store', $project), [
            'title' => 'Badge task',
            'description' => null,
            'status' => 'todo',
            'priority' => 'medium',
            'due_date' => null,
        ]);

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Notifications', false)
            ->assertSee('bg-indigo-600', false);
    }

    public function test_task_created_notification_implements_should_queue(): void
    {
        $notification = new TaskCreatedNotification(Task::factory()->make(), User::factory()->make());

        $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class, $notification);
    }

    public function test_comments_tags_and_attachments_regression_still_work(): void
    {
        Storage::fake(TaskAttachment::STORAGE_DISK);
        ['owner' => $owner, 'project' => $project, 'task' => $task] = $this->ownedTask();

        $this->actingAs($owner)
            ->post(route('projects.tasks.comments.store', [$project, $task]), ['body' => 'Still ok'])
            ->assertRedirect();

        $this->actingAs($owner)
            ->post(route('projects.tasks.tags.store', [$project, $task]), ['name' => 'still'])
            ->assertRedirect();

        $this->actingAs($owner)
            ->post(route('projects.tasks.attachments.store', [$project, $task]), [
                'file' => UploadedFile::fake()->create('ok.pdf', 5, 'application/pdf'),
            ])
            ->assertRedirect();
    }
}
