<?php

namespace Tests\Feature\Api;

use App\Events\CommentCreated;
use App\Models\Comment;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\CreatesWorkBoardFixtures;
use Tests\Concerns\InteractsWithSanctum;
use Tests\TestCase;

class ApiCommentTest extends TestCase
{
    use CreatesWorkBoardFixtures;
    use InteractsWithSanctum;
    use RefreshDatabase;

    public function test_lists_task_comments(): void
    {
        ['owner' => $owner, 'project' => $project, 'task' => $task] = $this->ownedTask();
        Comment::factory()->for($task)->for($owner)->create(['body' => 'Listed']);

        $this->actingAsSanctum($owner)
            ->getJson("/api/projects/{$project->id}/tasks/{$task->id}/comments")
            ->assertOk()
            ->assertJsonPath('data.0.body', 'Listed');
    }

    public function test_creates_comment_and_dispatches_event(): void
    {
        Event::fake([CommentCreated::class]);
        ['member' => $member, 'project' => $project] = $this->sharedProject();
        $task = Task::factory()->for($project)->create();

        $this->actingAsSanctum($member)
            ->postJson("/api/projects/{$project->id}/tasks/{$task->id}/comments", [
                'body' => 'API comment',
            ])
            ->assertCreated()
            ->assertJsonPath('data.body', 'API comment');

        Event::assertDispatched(CommentCreated::class);
    }

    public function test_rejects_whitespace_only_comment_body(): void
    {
        ['owner' => $owner, 'project' => $project, 'task' => $task] = $this->ownedTask();

        $this->actingAsSanctum($owner)
            ->postJson("/api/projects/{$project->id}/tasks/{$task->id}/comments", [
                'body' => '   ',
            ])
            ->assertUnprocessable();
    }

    public function test_author_can_delete_own_comment(): void
    {
        ['member' => $member, 'project' => $project] = $this->sharedProject();
        $task = Task::factory()->for($project)->create();
        $comment = Comment::factory()->for($task)->for($member)->create();

        $this->actingAsSanctum($member)
            ->deleteJson("/api/projects/{$project->id}/tasks/{$task->id}/comments/{$comment->id}")
            ->assertNoContent();
    }

    public function test_owner_can_delete_another_users_comment(): void
    {
        ['owner' => $owner, 'member' => $member, 'project' => $project] = $this->sharedProject();
        $task = Task::factory()->for($project)->create();
        $comment = Comment::factory()->for($task)->for($member)->create();

        $this->actingAsSanctum($owner)
            ->deleteJson("/api/projects/{$project->id}/tasks/{$task->id}/comments/{$comment->id}")
            ->assertNoContent();
    }

    public function test_member_cannot_delete_another_members_comment(): void
    {
        ['member' => $member, 'project' => $project] = $this->sharedProject();
        $other = User::factory()->create();
        $this->attachProjectMember($project, $other);
        $task = Task::factory()->for($project)->create();
        $comment = Comment::factory()->for($task)->for($other)->create();

        $this->actingAsSanctum($member)
            ->deleteJson("/api/projects/{$project->id}/tasks/{$task->id}/comments/{$comment->id}")
            ->assertForbidden();
    }

    public function test_wrong_nested_comment_returns_404(): void
    {
        ['owner' => $owner, 'project' => $project] = $this->ownedProject();
        $otherTask = Task::factory()->for($project)->create();
        $task = Task::factory()->for($project)->create();
        $comment = Comment::factory()->for($task)->for($owner)->create();

        $this->actingAsSanctum($owner)
            ->deleteJson("/api/projects/{$project->id}/tasks/{$otherTask->id}/comments/{$comment->id}")
            ->assertNotFound();
    }
}
