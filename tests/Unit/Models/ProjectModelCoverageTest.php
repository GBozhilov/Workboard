<?php

namespace Tests\Unit\Models;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesWorkBoardFixtures;
use Tests\TestCase;

class ProjectModelCoverageTest extends TestCase
{
    use CreatesWorkBoardFixtures;
    use RefreshDatabase;

    public function test_project_belongs_to_owner(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $this->assertTrue($project->user->is($user));
    }

    public function test_project_has_many_tasks_relationship(): void
    {
        ['project' => $project] = $this->ownedProject();
        Task::factory()->for($project)->count(2)->create();

        $this->assertCount(2, $project->tasks);
    }

    public function test_project_has_members_relationship(): void
    {
        ['owner' => $owner, 'member' => $member, 'project' => $project] = $this->sharedProject();

        $memberIds = $project->members()->pluck('users.id');

        $this->assertTrue($memberIds->contains($owner->id));
        $this->assertTrue($memberIds->contains($member->id));
    }

    public function test_accessible_by_includes_owned_project(): void
    {
        $user = User::factory()->create();
        $owned = Project::factory()->for($user)->create();

        $this->assertTrue(Project::query()->accessibleBy($user)->whereKey($owned->id)->exists());
    }

    public function test_accessible_by_includes_member_project(): void
    {
        ['member' => $member, 'project' => $project] = $this->sharedProject();

        $this->assertTrue(Project::query()->accessibleBy($member)->whereKey($project->id)->exists());
    }

    public function test_accessible_by_excludes_unrelated_project(): void
    {
        ['project' => $project] = $this->ownedProject();
        $outsider = User::factory()->create();

        $this->assertFalse(Project::query()->accessibleBy($outsider)->whereKey($project->id)->exists());
    }

    public function test_search_scope_matches_partial_name(): void
    {
        $user = User::factory()->create();
        Project::factory()->for($user)->create(['name' => 'Findable project name']);

        $results = Project::query()->search('findable')->get();

        $this->assertCount(1, $results);
    }
}
