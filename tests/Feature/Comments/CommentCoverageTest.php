<?php

namespace Tests\Feature\Comments;

use App\Http\Requests\StoreCommentRequest;
use App\Models\Comment;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesWorkBoardFixtures;
use Tests\TestCase;

class CommentCoverageTest extends TestCase
{
    use CreatesWorkBoardFixtures;
    use RefreshDatabase;

    private function commentStoreUrl($project, $task): string
    {
        return route('projects.tasks.comments.store', [$project, $task]);
    }

    private function commentDestroyUrl($project, $task, $comment): string
    {
        return route('projects.tasks.comments.destroy', [$project, $task, $comment]);
    }

    public function test_comment_accepts_minimum_valid_body(): void
    {
        ['owner' => $owner, 'project' => $project, 'task' => $task] = $this->ownedTask();

        $this->actingAs($owner)
            ->post($this->commentStoreUrl($project, $task), ['body' => 'a'])
            ->assertRedirect();

        $this->assertDatabaseHas('comments', ['body' => 'a']);
    }

    public function test_comment_rejects_body_over_max_length(): void
    {
        ['owner' => $owner, 'project' => $project, 'task' => $task] = $this->ownedTask();

        $this->actingAs($owner)
            ->from(route('projects.tasks.show', [$project, $task]))
            ->post($this->commentStoreUrl($project, $task), [
                'body' => str_repeat('x', StoreCommentRequest::MAX_BODY_LENGTH + 1),
            ])
            ->assertSessionHasErrors('body');
    }

    public function test_comment_trims_surrounding_whitespace(): void
    {
        ['owner' => $owner, 'project' => $project, 'task' => $task] = $this->ownedTask();

        $this->actingAs($owner)
            ->post($this->commentStoreUrl($project, $task), ['body' => "  trimmed  \n"])
            ->assertRedirect();

        $this->assertDatabaseHas('comments', ['body' => 'trimmed']);
    }

    public function test_comment_stores_unicode_content(): void
    {
        ['owner' => $owner, 'project' => $project, 'task' => $task] = $this->ownedTask();
        $body = 'Здравей 🚀';

        $this->actingAs($owner)
            ->post($this->commentStoreUrl($project, $task), ['body' => $body])
            ->assertRedirect();

        $this->assertDatabaseHas('comments', ['body' => $body]);
    }

    public function test_comment_stores_multiline_content(): void
    {
        ['owner' => $owner, 'project' => $project, 'task' => $task] = $this->ownedTask();
        $body = "Line one\nLine two";

        $this->actingAs($owner)
            ->post($this->commentStoreUrl($project, $task), ['body' => $body])
            ->assertRedirect();

        $this->assertDatabaseHas('comments', ['body' => $body]);
    }

    public function test_task_show_escapes_html_in_comment_body(): void
    {
        ['owner' => $owner, 'project' => $project, 'task' => $task] = $this->ownedTask();
        Comment::factory()->for($task)->for($owner)->create([
            'body' => '<script>alert(1)</script>',
        ]);

        $this->actingAs($owner)
            ->get(route('projects.tasks.show', [$project, $task]))
            ->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_task_show_lists_comments_in_created_order(): void
    {
        ['owner' => $owner, 'project' => $project, 'task' => $task] = $this->ownedTask();
        Comment::factory()->for($task)->for($owner)->create([
            'body' => 'Older',
            'created_at' => now()->subHour(),
        ]);
        Comment::factory()->for($task)->for($owner)->create([
            'body' => 'Newer',
            'created_at' => now(),
        ]);

        $this->actingAs($owner)
            ->get(route('projects.tasks.show', [$project, $task]))
            ->assertSeeInOrder(['Older', 'Newer'], false);
    }

    public function test_deleting_task_cascades_comments(): void
    {
        ['owner' => $owner, 'project' => $project, 'task' => $task] = $this->ownedTask();
        Comment::factory()->for($task)->for($owner)->create();

        $this->actingAs($owner)->delete(route('projects.tasks.destroy', [$project, $task]));

        $this->assertDatabaseCount('comments', 0);
    }

    public function test_deleting_project_cascades_nested_comments(): void
    {
        ['owner' => $owner, 'project' => $project, 'task' => $task] = $this->ownedTask();
        Comment::factory()->for($task)->for($owner)->create();

        $this->actingAs($owner)->delete(route('projects.destroy', $project));

        $this->assertDatabaseCount('comments', 0);
    }

    public function test_comment_destroy_with_mismatched_project_returns_not_found(): void
    {
        ['owner' => $owner, 'project' => $project, 'task' => $task] = $this->ownedTask();
        $otherProject = Project::factory()->for($owner)->create();
        $comment = Comment::factory()->for($task)->for($owner)->create();

        $this->actingAs($owner)
            ->delete($this->commentDestroyUrl($otherProject, $task, $comment))
            ->assertNotFound();
    }

    public function test_user_comments_relationship_returns_authored_comments(): void
    {
        ['owner' => $owner, 'project' => $project, 'task' => $task] = $this->ownedTask();
        Comment::factory()->for($task)->for($owner)->count(2)->create();

        $this->assertCount(2, $owner->comments);
    }
}
