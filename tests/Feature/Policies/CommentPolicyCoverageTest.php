<?php

namespace Tests\Feature\Policies;

use App\Models\Comment;
use App\Models\Task;
use App\Models\User;
use App\Policies\CommentPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesWorkBoardFixtures;
use Tests\TestCase;

class CommentPolicyCoverageTest extends TestCase
{
    use CreatesWorkBoardFixtures;
    use RefreshDatabase;

    private CommentPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new CommentPolicy;
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function commentCreateProvider(): array
    {
        return [
            'owner' => ['owner', true],
            'member' => ['member', true],
            'outsider' => ['outsider', false],
        ];
    }

    #[DataProvider('commentCreateProvider')]
    public function test_comment_create_policy(string $actorType, bool $expected): void
    {
        ['owner' => $owner, 'member' => $member, 'project' => $project] = $this->sharedProject();
        $task = Task::factory()->for($project)->create();
        $outsider = User::factory()->create();

        $actor = match ($actorType) {
            'owner' => $owner,
            'member' => $member,
            default => $outsider,
        };

        $this->assertSame($expected, $this->policy->create($actor, $task));
    }

    public function test_comment_author_can_delete_via_policy(): void
    {
        ['owner' => $owner, 'project' => $project, 'task' => $task] = $this->ownedTask();
        $comment = Comment::factory()->for($task)->for($owner)->create();

        $this->assertTrue($this->policy->delete($owner, $comment));
    }

    public function test_project_owner_can_delete_member_comment_via_policy(): void
    {
        ['owner' => $owner, 'member' => $member, 'project' => $project] = $this->sharedProject();
        $task = Task::factory()->for($project)->create();
        $comment = Comment::factory()->for($task)->for($member)->create();

        $this->assertTrue($this->policy->delete($owner, $comment));
    }

    public function test_member_cannot_delete_another_members_comment_via_policy(): void
    {
        ['member' => $member, 'project' => $project] = $this->sharedProject();
        $other = User::factory()->create();
        $this->attachProjectMember($project, $other);
        $task = Task::factory()->for($project)->create();
        $comment = Comment::factory()->for($task)->for($other)->create();

        $this->assertFalse($this->policy->delete($member, $comment));
    }

    public function test_outsider_cannot_delete_comment_via_policy(): void
    {
        ['owner' => $owner, 'project' => $project, 'task' => $task] = $this->ownedTask();
        $comment = Comment::factory()->for($task)->for($owner)->create();
        $outsider = User::factory()->create();

        $this->assertFalse($this->policy->delete($outsider, $comment));
    }
}
