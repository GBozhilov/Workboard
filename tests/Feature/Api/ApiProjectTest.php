<?php

namespace Tests\Feature\Api;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesWorkBoardFixtures;
use Tests\Concerns\InteractsWithSanctum;
use Tests\TestCase;

class ApiProjectTest extends TestCase
{
    use CreatesWorkBoardFixtures;
    use InteractsWithSanctum;
    use RefreshDatabase;

    public function test_lists_only_accessible_projects(): void
    {
        ['owner' => $owner, 'project' => $shared] = $this->sharedProject();
        $secret = Project::factory()->create();
        $outsider = User::factory()->create();

        $this->actingAsSanctum($owner)
            ->getJson('/api/projects')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $shared->id);

        $this->actingAsSanctum($outsider)
            ->getJson('/api/projects')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_project_index_supports_pagination(): void
    {
        $owner = User::factory()->create();
        Project::factory()->count(3)->for($owner)->create();

        $this->actingAsSanctum($owner)
            ->getJson('/api/projects?per_page=2')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonCount(2, 'data');
    }

    public function test_owner_can_create_project(): void
    {
        $owner = User::factory()->create();

        $this->actingAsSanctum($owner)
            ->postJson('/api/projects', [
                'name' => 'API Project',
                'description' => 'From API',
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'API Project');
    }

    public function test_project_create_validates_name(): void
    {
        $owner = User::factory()->create();

        $this->actingAsSanctum($owner)
            ->postJson('/api/projects', ['name' => ''])
            ->assertUnprocessable();
    }

    public function test_member_can_view_project_with_role_and_summary(): void
    {
        ['owner' => $owner, 'member' => $member, 'project' => $project] = $this->sharedProject();

        $this->actingAsSanctum($member)
            ->getJson("/api/projects/{$project->id}")
            ->assertOk()
            ->assertJsonPath('data.role', 'member')
            ->assertJsonStructure(['data' => ['summary' => ['total_tasks', 'member_count']]]);
    }

    public function test_outsider_cannot_view_project(): void
    {
        ['project' => $project] = $this->ownedProject();
        $outsider = User::factory()->create();

        $this->actingAsSanctum($outsider)
            ->getJson("/api/projects/{$project->id}")
            ->assertForbidden();
    }

    public function test_owner_can_update_project(): void
    {
        ['owner' => $owner, 'project' => $project] = $this->ownedProject();

        $this->actingAsSanctum($owner)
            ->patchJson("/api/projects/{$project->id}", ['name' => 'Renamed API'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed API');
    }

    public function test_member_cannot_update_project(): void
    {
        ['member' => $member, 'project' => $project] = $this->sharedProject();

        $this->actingAsSanctum($member)
            ->patchJson("/api/projects/{$project->id}", ['name' => 'Hijack'])
            ->assertForbidden();
    }

    public function test_owner_can_delete_project(): void
    {
        ['owner' => $owner, 'project' => $project] = $this->ownedProject();

        $this->actingAsSanctum($owner)
            ->deleteJson("/api/projects/{$project->id}")
            ->assertNoContent();

        $this->assertModelMissing($project);
    }

    public function test_member_cannot_delete_project(): void
    {
        ['member' => $member, 'project' => $project] = $this->sharedProject();

        $this->actingAsSanctum($member)
            ->deleteJson("/api/projects/{$project->id}")
            ->assertForbidden();
    }
}
