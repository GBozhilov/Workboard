<?php

namespace Tests\Unit\Models;

use App\Models\Comment;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesWorkBoardFixtures;
use Tests\TestCase;

class UserModelCoverageTest extends TestCase
{
    use CreatesWorkBoardFixtures;
    use RefreshDatabase;

    public function test_user_has_many_owned_projects(): void
    {
        $user = User::factory()->create();
        Project::factory()->for($user)->count(2)->create();

        $this->assertCount(2, $user->projects);
    }

    public function test_user_member_projects_includes_pivot_role(): void
    {
        ['member' => $member, 'project' => $project] = $this->sharedProject();

        $memberProject = $member->memberProjects()->whereKey($project->id)->first();

        $this->assertNotNull($memberProject);
        $this->assertSame('member', $memberProject->pivot->role);
    }

    public function test_user_has_many_comments(): void
    {
        ['owner' => $owner, 'task' => $task] = $this->ownedTask();
        Comment::factory()->for($task)->for($owner)->count(2)->create();

        $this->assertCount(2, $owner->comments);
    }

    public function test_user_password_is_hidden_from_array(): void
    {
        $user = User::factory()->create();

        $this->assertArrayNotHasKey('password', $user->toArray());
    }

    public function test_user_password_is_hashed_when_set(): void
    {
        $user = User::factory()->create(['password' => 'plain-password']);

        $this->assertNotSame('plain-password', $user->password);
    }
}
