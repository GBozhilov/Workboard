<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Tests\Concerns\CreatesWorkBoardFixtures;
use Tests\TestCase;

class NotificationNavigationTest extends TestCase
{
    use CreatesWorkBoardFixtures;
    use RefreshDatabase;

    public function test_open_redirects_to_task_when_task_exists(): void
    {
        ['owner' => $owner, 'member' => $member, 'project' => $project] = $this->sharedProject();
        $task = Task::factory()->for($project)->create(['title' => 'Open me']);

        $this->actingAs($member)->post(route('projects.tasks.comments.store', [$project, $task]), [
            'body' => 'Navigate here',
        ]);

        $notification = $owner->fresh()->notifications()->firstOrFail();

        $this->actingAs($owner)
            ->get(route('notifications.open', $notification))
            ->assertRedirect(route('projects.tasks.show', [$project, $task]).'#comments');
    }

    public function test_open_redirects_to_project_when_task_deleted(): void
    {
        ['owner' => $owner, 'member' => $member, 'project' => $project] = $this->sharedProject();
        $task = Task::factory()->for($project)->create(['title' => 'Gone task']);

        $this->actingAs($member)->post(route('projects.tasks.comments.store', [$project, $task]), [
            'body' => 'Before delete',
        ]);

        $notification = $owner->fresh()->notifications()->firstOrFail();
        $task->delete();

        $this->actingAs($owner)
            ->get(route('notifications.open', $notification))
            ->assertRedirect(route('projects.show', $project))
            ->assertSessionHas('status', 'This task no longer exists.');
    }

    public function test_open_redirects_to_notifications_when_task_and_project_deleted(): void
    {
        ['owner' => $owner, 'member' => $member, 'project' => $project] = $this->sharedProject();
        $task = Task::factory()->for($project)->create();

        $this->actingAs($member)->post(route('projects.tasks.comments.store', [$project, $task]), [
            'body' => 'Stale',
        ]);

        $notification = $owner->fresh()->notifications()->firstOrFail();
        $project->delete();

        $this->actingAs($owner)
            ->get(route('notifications.open', $notification))
            ->assertRedirect(route('notifications.index'))
            ->assertSessionHas('status', 'The referenced item is no longer available.');
    }

    public function test_project_member_notification_open_when_project_deleted(): void
    {
        ['owner' => $owner, 'project' => $project] = $this->ownedProject();
        $member = User::factory()->create();

        $this->actingAs($owner)->post(route('projects.members.store', $project), [
            'email' => $member->email,
            'role' => 'member',
        ]);

        $notification = $member->fresh()->notifications()->firstOrFail();
        $project->delete();

        $this->actingAs($member)
            ->get(route('notifications.open', $notification))
            ->assertRedirect(route('notifications.index'))
            ->assertSessionHas('status', 'The referenced project is no longer available.');
    }

    public function test_open_task_notification_when_user_lost_project_access(): void
    {
        ['owner' => $owner, 'member' => $member, 'project' => $project] = $this->sharedProject();
        $task = Task::factory()->for($project)->create(['title' => 'Was visible']);

        $notification = $member->notifications()->create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'type' => \App\Notifications\TaskCreatedNotification::class,
            'data' => [
                'type' => 'task_created',
                'project_id' => $project->id,
                'project_name' => $project->name,
                'task_id' => $task->id,
                'task_title' => $task->title,
                'actor_id' => $owner->id,
                'actor_name' => $owner->name,
                'message' => 'Stale task access',
            ],
        ]);

        $project->members()->detach($member->id);

        $this->actingAs($member)
            ->get(route('notifications.open', $notification))
            ->assertRedirect(route('notifications.index'))
            ->assertSessionHas('status', 'The referenced item is no longer available.');
    }

    public function test_user_cannot_open_another_users_notification(): void
    {
        ['owner' => $owner, 'member' => $member, 'project' => $project] = $this->sharedProject();
        $task = Task::factory()->for($project)->create();

        $this->actingAs($member)->post(route('projects.tasks.comments.store', [$project, $task]), [
            'body' => 'Private inbox',
        ]);

        $notification = $owner->fresh()->notifications()->firstOrFail();

        $this->actingAs($member)
            ->get(route('notifications.open', $notification))
            ->assertNotFound();
    }

    public function test_stale_notification_remains_visible_after_task_deleted(): void
    {
        ['owner' => $owner, 'member' => $member, 'project' => $project] = $this->sharedProject();
        $task = Task::factory()->for($project)->create(['title' => 'Historical']);

        $this->actingAs($member)->post(route('projects.tasks.comments.store', [$project, $task]), [
            'body' => 'Keep history',
        ]);

        $notificationId = $owner->fresh()->notifications()->firstOrFail()->id;
        $task->delete();

        $this->actingAs($owner)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Historical', false);

        $this->assertDatabaseHas('notifications', ['id' => $notificationId]);
    }

    public function test_opening_stale_notification_marks_it_as_read(): void
    {
        ['owner' => $owner, 'member' => $member, 'project' => $project] = $this->sharedProject();
        $task = Task::factory()->for($project)->create();

        $this->actingAs($member)->post(route('projects.tasks.comments.store', [$project, $task]), [
            'body' => 'Read on open',
        ]);

        $notification = $owner->fresh()->notifications()->firstOrFail();
        $this->assertNull($notification->read_at);

        $task->delete();

        $this->actingAs($owner)->get(route('notifications.open', $notification));

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_task_from_wrong_project_in_notification_data_falls_back_safely(): void
    {
        ['owner' => $owner, 'project' => $projectA] = $this->ownedProject();
        $projectB = Project::factory()->for($owner)->create();
        $taskOnB = Task::factory()->for($projectB)->create();

        $notification = DatabaseNotification::query()->create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'type' => \App\Notifications\CommentCreatedNotification::class,
            'notifiable_type' => User::class,
            'notifiable_id' => $owner->id,
            'data' => [
                'type' => 'comment_created',
                'project_id' => $projectA->id,
                'project_name' => $projectA->name,
                'task_id' => $taskOnB->id,
                'task_title' => $taskOnB->title,
                'comment_id' => 1,
                'actor_id' => $owner->id,
                'actor_name' => $owner->name,
                'message' => 'Mismatch payload',
            ],
        ]);

        $this->actingAs($owner)
            ->get(route('notifications.open', $notification))
            ->assertRedirect(route('projects.show', $projectA))
            ->assertSessionHas('status', 'This task no longer exists.');
    }

    public function test_task_created_notification_open_redirects_without_fragment(): void
    {
        ['owner' => $owner, 'member' => $member, 'project' => $project] = $this->sharedProject();

        $this->actingAs($member)->post(route('projects.tasks.store', $project), [
            'title' => 'Direct open',
            'description' => null,
            'status' => 'todo',
            'priority' => 'medium',
            'due_date' => null,
        ]);

        $notification = $owner->fresh()->notifications()->firstOrFail();
        $task = $project->tasks()->where('title', 'Direct open')->firstOrFail();

        $this->actingAs($owner)
            ->get(route('notifications.open', $notification))
            ->assertRedirect(route('projects.tasks.show', [$project, $task]));
    }
}
