<?php

namespace Tests\Unit\Models;

use App\Models\Comment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesWorkBoardFixtures;
use Tests\TestCase;

class CommentModelCoverageTest extends TestCase
{
    use CreatesWorkBoardFixtures;
    use RefreshDatabase;

    public function test_comment_belongs_to_task(): void
    {
        ['owner' => $owner, 'task' => $task] = $this->ownedTask();
        $comment = Comment::factory()->for($task)->for($owner)->create();

        $this->assertTrue($comment->task->is($task));
    }

    public function test_comment_belongs_to_user(): void
    {
        ['owner' => $owner, 'task' => $task] = $this->ownedTask();
        $comment = Comment::factory()->for($task)->for($owner)->create();

        $this->assertTrue($comment->user->is($owner));
    }

    public function test_comment_can_be_created_through_task_relationship(): void
    {
        ['owner' => $owner, 'task' => $task] = $this->ownedTask();

        $comment = $task->comments()->create([
            'body' => 'Created via relationship',
            'user_id' => $owner->id,
        ]);

        $this->assertDatabaseHas('comments', [
            'id' => $comment->id,
            'task_id' => $task->id,
            'user_id' => $owner->id,
        ]);
    }
}
