<?php

namespace Tests\Feature;

use App\Enums\ProjectRole;
use App\Http\Requests\StoreCommentRequest;
use App\Models\Comment;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommentTest extends TestCase
{
    use RefreshDatabase;

    private function addMember(Project $project, User $member): void
    {
        $project->members()->attach($member->id, [
            'role' => ProjectRole::Member->value,
        ]);
    }

    /**
     * @return array{owner: User, project: Project, task: Task}
     */
    private function ownedTaskSetup(): array
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $task = Task::factory()->for($project)->create();

        return ['owner' => $owner, 'project' => $project, 'task' => $task];
    }

    private function commentStoreUrl(Project $project, Task $task): string
    {
        return route('projects.tasks.comments.store', [$project, $task]);
    }

    private function commentDestroyUrl(Project $project, Task $task, Comment $comment): string
    {
        return route('projects.tasks.comments.destroy', [$project, $task, $comment]);
    }

    /**
     * Laravel disables CSRF during PHPUnit inside PreventRequestForgery::runningUnitTests().
     * Swap in an enforcing instance so we can assert real token validation (419 / success).
     */
    private function enforceRequestForgeryProtection(): void
    {
        $app = $this->app;

        $middleware = new class($app, $app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests(): bool
            {
                return false;
            }
        };

        $this->app->instance(PreventRequestForgery::class, $middleware);
        $this->app->instance(ValidateCsrfToken::class, $middleware);
    }

    public function test_project_owner_can_create_a_comment(): void
    {
        ['owner' => $owner, 'project' => $project, 'task' => $task] = $this->ownedTaskSetup();

        $this->actingAs($owner)
            ->post($this->commentStoreUrl($project, $task), ['body' => 'Owner note'])
            ->assertRedirect(route('projects.tasks.show', [$project, $task]))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('comments', [
            'task_id' => $task->id,
            'user_id' => $owner->id,
            'body' => 'Owner note',
        ]);
    }

    public function test_project_member_can_create_a_comment(): void
    {
        ['owner' => $owner, 'project' => $project, 'task' => $task] = $this->ownedTaskSetup();
        $member = User::factory()->create();
        $this->addMember($project, $member);

        $this->actingAs($member)
            ->post($this->commentStoreUrl($project, $task), ['body' => 'Member note'])
            ->assertRedirect();

        $this->assertDatabaseHas('comments', [
            'task_id' => $task->id,
            'user_id' => $member->id,
            'body' => 'Member note',
        ]);
    }

    public function test_outsider_cannot_create_a_comment(): void
    {
        ['project' => $project, 'task' => $task] = $this->ownedTaskSetup();
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->post($this->commentStoreUrl($project, $task), ['body' => 'Nope'])
            ->assertForbidden();

        $this->assertDatabaseCount('comments', 0);
    }

    public function test_guest_cannot_create_a_comment(): void
    {
        ['project' => $project, 'task' => $task] = $this->ownedTaskSetup();

        $this->post($this->commentStoreUrl($project, $task), ['body' => 'Guest'])
            ->assertRedirect(route('login'));

        $this->assertDatabaseCount('comments', 0);
    }

    public function test_comment_author_can_delete_own_comment(): void
    {
        ['owner' => $owner, 'project' => $project, 'task' => $task] = $this->ownedTaskSetup();
        $comment = Comment::factory()->for($task)->for($owner)->create(['body' => 'Mine']);

        $this->actingAs($owner)
            ->delete($this->commentDestroyUrl($project, $task, $comment))
            ->assertRedirect(route('projects.tasks.show', [$project, $task]));

        $this->assertDatabaseMissing('comments', ['id' => $comment->id]);
    }

    public function test_project_owner_can_delete_another_users_comment(): void
    {
        ['owner' => $owner, 'project' => $project, 'task' => $task] = $this->ownedTaskSetup();
        $member = User::factory()->create();
        $this->addMember($project, $member);
        $comment = Comment::factory()->for($task)->for($member)->create(['body' => 'Member wrote this']);

        $this->actingAs($owner)
            ->delete($this->commentDestroyUrl($project, $task, $comment))
            ->assertRedirect();

        $this->assertDatabaseMissing('comments', ['id' => $comment->id]);
    }

    public function test_regular_member_cannot_delete_another_users_comment(): void
    {
        ['owner' => $owner, 'project' => $project, 'task' => $task] = $this->ownedTaskSetup();
        $member = User::factory()->create();
        $otherMember = User::factory()->create();
        $this->addMember($project, $member);
        $this->addMember($project, $otherMember);
        $comment = Comment::factory()->for($task)->for($owner)->create();

        $this->actingAs($member)
            ->delete($this->commentDestroyUrl($project, $task, $comment))
            ->assertForbidden();

        $this->assertDatabaseHas('comments', ['id' => $comment->id]);
    }

    public function test_outsider_cannot_delete_a_comment(): void
    {
        ['owner' => $owner, 'project' => $project, 'task' => $task] = $this->ownedTaskSetup();
        $outsider = User::factory()->create();
        $comment = Comment::factory()->for($task)->for($owner)->create();

        $this->actingAs($outsider)
            ->delete($this->commentDestroyUrl($project, $task, $comment))
            ->assertForbidden();

        $this->assertDatabaseHas('comments', ['id' => $comment->id]);
    }

    public function test_guest_cannot_delete_a_comment(): void
    {
        ['owner' => $owner, 'project' => $project, 'task' => $task] = $this->ownedTaskSetup();
        $comment = Comment::factory()->for($task)->for($owner)->create();

        $this->delete($this->commentDestroyUrl($project, $task, $comment))
            ->assertRedirect(route('login'));

        $this->assertDatabaseHas('comments', ['id' => $comment->id]);
    }

    public function test_empty_comment_body_is_rejected(): void
    {
        ['owner' => $owner, 'project' => $project, 'task' => $task] = $this->ownedTaskSetup();

        $this->actingAs($owner)
            ->from(route('projects.tasks.show', [$project, $task]))
            ->post($this->commentStoreUrl($project, $task), ['body' => ''])
            ->assertRedirect(route('projects.tasks.show', [$project, $task]))
            ->assertSessionHasErrors('body');

        $this->assertDatabaseCount('comments', 0);
    }

    public function test_whitespace_only_comment_body_is_rejected(): void
    {
        ['owner' => $owner, 'project' => $project, 'task' => $task] = $this->ownedTaskSetup();

        $this->actingAs($owner)
            ->from(route('projects.tasks.show', [$project, $task]))
            ->post($this->commentStoreUrl($project, $task), ['body' => "  \t\n  "])
            ->assertSessionHasErrors('body');

        $this->assertDatabaseCount('comments', 0);
    }

    public function test_comment_body_exceeding_max_length_is_rejected(): void
    {
        ['owner' => $owner, 'project' => $project, 'task' => $task] = $this->ownedTaskSetup();
        $tooLong = str_repeat('a', StoreCommentRequest::MAX_BODY_LENGTH + 1);

        $this->actingAs($owner)
            ->from(route('projects.tasks.show', [$project, $task]))
            ->post($this->commentStoreUrl($project, $task), ['body' => $tooLong])
            ->assertSessionHasErrors('body');

        $this->assertDatabaseCount('comments', 0);
    }

    public function test_comment_body_at_max_length_is_accepted(): void
    {
        ['owner' => $owner, 'project' => $project, 'task' => $task] = $this->ownedTaskSetup();
        $body = str_repeat('b', StoreCommentRequest::MAX_BODY_LENGTH);

        $this->actingAs($owner)
            ->post($this->commentStoreUrl($project, $task), ['body' => $body])
            ->assertRedirect();

        $this->assertDatabaseHas('comments', [
            'task_id' => $task->id,
            'body' => $body,
        ]);
    }

    public function test_comment_is_stored_with_correct_task_id(): void
    {
        ['owner' => $owner, 'project' => $project, 'task' => $task] = $this->ownedTaskSetup();

        $this->actingAs($owner)
            ->post($this->commentStoreUrl($project, $task), ['body' => 'Linked to task']);

        $comment = Comment::first();
        $this->assertNotNull($comment);
        $this->assertSame($task->id, $comment->task_id);
    }

    public function test_comment_is_stored_with_correct_user_id(): void
    {
        ['owner' => $owner, 'project' => $project, 'task' => $task] = $this->ownedTaskSetup();

        $this->actingAs($owner)
            ->post($this->commentStoreUrl($project, $task), ['body' => 'Authored']);

        $comment = Comment::first();
        $this->assertNotNull($comment);
        $this->assertSame($owner->id, $comment->user_id);
    }

    public function test_task_has_many_comments_relationship(): void
    {
        ['project' => $project, 'task' => $task] = $this->ownedTaskSetup();
        Comment::factory()->for($task)->count(3)->create();

        $task->refresh()->load('comments');

        $this->assertCount(3, $task->comments);
    }

    public function test_comment_belongs_to_task_relationship(): void
    {
        ['project' => $project, 'task' => $task] = $this->ownedTaskSetup();
        $comment = Comment::factory()->for($task)->create();

        $this->assertTrue($comment->task->is($task));
    }

    public function test_comment_belongs_to_user_relationship(): void
    {
        $user = User::factory()->create();
        ['project' => $project, 'task' => $task] = $this->ownedTaskSetup();
        $comment = Comment::factory()->for($task)->for($user)->create();

        $this->assertTrue($comment->user->is($user));
    }

    public function test_deleting_task_cascades_to_comments(): void
    {
        ['project' => $project, 'task' => $task] = $this->ownedTaskSetup();
        Comment::factory()->for($task)->count(2)->create();

        $task->delete();

        $this->assertDatabaseCount('comments', 0);
    }

    public function test_deleting_user_cascades_to_comments(): void
    {
        $user = User::factory()->create();
        ['project' => $project, 'task' => $task] = $this->ownedTaskSetup();
        Comment::factory()->for($task)->for($user)->create();

        $user->delete();

        $this->assertDatabaseCount('comments', 0);
    }

    public function test_cannot_create_comment_when_nested_task_belongs_to_another_project(): void
    {
        ['owner' => $owner, 'project' => $project, 'task' => $task] = $this->ownedTaskSetup();
        $otherProject = Project::factory()->for($owner)->create();

        $this->actingAs($owner)
            ->post($this->commentStoreUrl($otherProject, $task), ['body' => 'Wrong nest'])
            ->assertNotFound();

        $this->assertDatabaseCount('comments', 0);
    }

    public function test_cannot_delete_comment_through_mismatched_task_in_url(): void
    {
        ['owner' => $owner, 'project' => $project, 'task' => $task] = $this->ownedTaskSetup();
        $otherTask = Task::factory()->for($project)->create();
        $comment = Comment::factory()->for($task)->for($owner)->create();

        $this->actingAs($owner)
            ->delete($this->commentDestroyUrl($project, $otherTask, $comment))
            ->assertNotFound();

        $this->assertDatabaseHas('comments', ['id' => $comment->id]);
    }

    public function test_task_page_does_not_show_comments_from_other_tasks(): void
    {
        ['owner' => $owner, 'project' => $project, 'task' => $task] = $this->ownedTaskSetup();
        $otherTask = Task::factory()->for($project)->create();
        Comment::factory()->for($otherTask)->for($owner)->create(['body' => 'OTHER_TASK_ONLY']);
        Comment::factory()->for($task)->for($owner)->create(['body' => 'THIS_TASK_ONLY']);

        $response = $this->actingAs($owner)
            ->get(route('projects.tasks.show', [$project, $task]));

        $response->assertOk()
            ->assertSee('THIS_TASK_ONLY', false)
            ->assertDontSee('OTHER_TASK_ONLY', false);
    }

    public function test_task_page_shows_existing_comments_section(): void
    {
        ['owner' => $owner, 'project' => $project, 'task' => $task] = $this->ownedTaskSetup();
        Comment::factory()->for($task)->for($owner)->create(['body' => 'Visible body']);

        $this->actingAs($owner)
            ->get(route('projects.tasks.show', [$project, $task]))
            ->assertOk()
            ->assertSee('Comments', false)
            ->assertSee('Visible body', false);
    }

    public function test_task_page_displays_comment_author_name(): void
    {
        ['owner' => $owner, 'project' => $project, 'task' => $task] = $this->ownedTaskSetup();
        $author = User::factory()->create(['name' => 'Comment Author']);
        Comment::factory()->for($task)->for($author)->create();

        $this->actingAs($owner)
            ->get(route('projects.tasks.show', [$project, $task]))
            ->assertSee('Comment Author', false);
    }

    public function test_task_page_displays_comment_body(): void
    {
        ['owner' => $owner, 'project' => $project, 'task' => $task] = $this->ownedTaskSetup();
        Comment::factory()->for($task)->for($owner)->create(['body' => 'Rendered comment text']);

        $this->actingAs($owner)
            ->get(route('projects.tasks.show', [$project, $task]))
            ->assertSee('Rendered comment text', false);
    }

    public function test_task_page_renders_multiple_comments(): void
    {
        ['owner' => $owner, 'project' => $project, 'task' => $task] = $this->ownedTaskSetup();
        Comment::factory()->for($task)->for($owner)->create(['body' => 'Comment A']);
        Comment::factory()->for($task)->for($owner)->create(['body' => 'Comment B']);

        $this->actingAs($owner)
            ->get(route('projects.tasks.show', [$project, $task]))
            ->assertSee('Comment A', false)
            ->assertSee('Comment B', false);
    }

    public function test_comments_appear_in_chronological_order_on_task_page(): void
    {
        ['owner' => $owner, 'project' => $project, 'task' => $task] = $this->ownedTaskSetup();

        Comment::factory()->for($task)->for($owner)->create([
            'body' => 'Oldest comment',
            'created_at' => now()->subHours(2),
        ]);
        Comment::factory()->for($task)->for($owner)->create([
            'body' => 'Middle comment',
            'created_at' => now()->subHour(),
        ]);
        Comment::factory()->for($task)->for($owner)->create([
            'body' => 'Newest comment',
            'created_at' => now(),
        ]);

        $this->actingAs($owner)
            ->get(route('projects.tasks.show', [$project, $task]))
            ->assertSeeInOrder(['Oldest comment', 'Middle comment', 'Newest comment'], false);
    }

    public function test_delete_control_is_visible_for_comment_author(): void
    {
        ['owner' => $owner, 'project' => $project, 'task' => $task] = $this->ownedTaskSetup();
        $comment = Comment::factory()->for($task)->for($owner)->create();

        $deleteUrl = $this->commentDestroyUrl($project, $task, $comment);

        $this->actingAs($owner)
            ->get(route('projects.tasks.show', [$project, $task]))
            ->assertSee($deleteUrl, false)
            ->assertSee('Delete', false);
    }

    public function test_delete_control_is_visible_for_project_owner_on_others_comment(): void
    {
        ['owner' => $owner, 'project' => $project, 'task' => $task] = $this->ownedTaskSetup();
        $member = User::factory()->create();
        $this->addMember($project, $member);
        $comment = Comment::factory()->for($task)->for($member)->create();

        $deleteUrl = $this->commentDestroyUrl($project, $task, $comment);

        $this->actingAs($owner)
            ->get(route('projects.tasks.show', [$project, $task]))
            ->assertSee($deleteUrl, false);
    }

    public function test_delete_control_is_not_rendered_for_member_on_another_users_comment(): void
    {
        ['owner' => $owner, 'project' => $project, 'task' => $task] = $this->ownedTaskSetup();
        $member = User::factory()->create();
        $this->addMember($project, $member);
        $comment = Comment::factory()->for($task)->for($owner)->create();

        $deleteUrl = $this->commentDestroyUrl($project, $task, $comment);

        $this->actingAs($member)
            ->get(route('projects.tasks.show', [$project, $task]))
            ->assertDontSee($deleteUrl, false);
    }

    public function test_comment_html_is_escaped_on_task_page(): void
    {
        ['owner' => $owner, 'project' => $project, 'task' => $task] = $this->ownedTaskSetup();
        $payload = '<script>alert("xss")</script>';
        Comment::factory()->for($task)->for($owner)->create(['body' => $payload]);

        $response = $this->actingAs($owner)
            ->get(route('projects.tasks.show', [$project, $task]));

        $response->assertOk();
        $response->assertDontSee($payload, false);
        $response->assertSee(e($payload), false);
    }

    public function test_comment_store_requires_valid_csrf_token_when_middleware_enabled(): void
    {
        ['owner' => $owner, 'project' => $project, 'task' => $task] = $this->ownedTaskSetup();

        $this->enforceRequestForgeryProtection();

        $this->actingAs($owner)
            ->withHeader('Sec-Fetch-Site', 'cross-site')
            ->post($this->commentStoreUrl($project, $task), ['body' => 'No token'])
            ->assertStatus(419);

        $this->enforceRequestForgeryProtection();

        $this->actingAs($owner)
            ->get(route('projects.tasks.show', [$project, $task]));

        $token = session()->token();

        $this->actingAs($owner)
            ->withHeader('Sec-Fetch-Site', 'cross-site')
            ->post($this->commentStoreUrl($project, $task), [
                'body' => 'With token',
                '_token' => $token,
            ])
            ->assertRedirect(route('projects.tasks.show', [$project, $task]));

        $this->assertDatabaseHas('comments', ['body' => 'With token']);
    }
}
