<?php

namespace Tests\Feature\Projects;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesWorkBoardFixtures;
use Tests\TestCase;

class ProjectCrudCoverageTest extends TestCase
{
    use CreatesWorkBoardFixtures;
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validProjectPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Coverage project',
            'description' => null,
        ], $overrides);
    }

    public function test_guest_cannot_create_project(): void
    {
        $this->post(route('projects.store'), $this->validProjectPayload())
            ->assertRedirect(route('login'));
    }

    public function test_create_project_with_minimal_valid_data(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('projects.store'), $this->validProjectPayload(['name' => 'Minimal']))
            ->assertRedirect();

        $this->assertDatabaseHas('projects', ['name' => 'Minimal', 'user_id' => $user->id]);
    }

    public function test_create_project_with_description(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('projects.store'), $this->validProjectPayload([
                'name' => 'With description',
                'description' => 'Details here',
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('projects', [
            'name' => 'With description',
            'description' => 'Details here',
        ]);
    }

    public function test_create_project_with_empty_description(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('projects.store'), $this->validProjectPayload([
                'name' => 'Empty desc',
                'description' => '',
            ]))
            ->assertRedirect();
    }

    public function test_project_store_creates_owner_membership_row(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('projects.store'), $this->validProjectPayload(['name' => 'Owner pivot']));

        $project = Project::where('name', 'Owner pivot')->first();
        $this->assertNotNull($project);
        $this->assertSame(
            ProjectRole::Owner->value,
            $project->members()->whereKey($user->id)->value('role')
        );
    }

    public function test_update_project_name_only(): void
    {
        ['owner' => $owner, 'project' => $project] = $this->ownedProject(projectAttributes: ['name' => 'Before']);

        $this->actingAs($owner)
            ->put(route('projects.update', $project), [
                'name' => 'After rename',
                'description' => $project->description,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('projects', ['id' => $project->id, 'name' => 'After rename']);
    }

    public function test_update_project_description_only(): void
    {
        ['owner' => $owner, 'project' => $project] = $this->ownedProject(projectAttributes: [
            'name' => 'Stable name',
            'description' => 'Old',
        ]);

        $this->actingAs($owner)
            ->put(route('projects.update', $project), [
                'name' => 'Stable name',
                'description' => 'New description',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'description' => 'New description',
        ]);
    }

    public function test_update_project_can_clear_description(): void
    {
        ['owner' => $owner, 'project' => $project] = $this->ownedProject(projectAttributes: [
            'name' => 'Clear desc',
            'description' => 'Will be cleared',
        ]);

        $this->actingAs($owner)
            ->put(route('projects.update', $project), [
                'name' => 'Clear desc',
                'description' => null,
            ])
            ->assertRedirect();

        $project->refresh();
        $this->assertNull($project->description);
    }

    public function test_project_name_at_max_length_is_accepted_on_create(): void
    {
        $user = User::factory()->create();
        $name = str_repeat('p', 255);

        $this->actingAs($user)
            ->post(route('projects.store'), $this->validProjectPayload(['name' => $name]))
            ->assertRedirect();

        $this->assertDatabaseHas('projects', ['name' => $name]);
    }

    public function test_project_name_over_max_length_is_rejected_on_create(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('projects.create'))
            ->post(route('projects.store'), $this->validProjectPayload([
                'name' => str_repeat('p', 256),
            ]))
            ->assertSessionHasErrors('name');
    }

    public function test_member_cannot_update_project(): void
    {
        ['member' => $member, 'project' => $project] = $this->sharedProject(['name' => 'Shared']);

        $this->actingAs($member)
            ->put(route('projects.update', $project), [
                'name' => 'Hijacked',
                'description' => null,
            ])
            ->assertForbidden();
    }

    public function test_member_cannot_delete_project(): void
    {
        ['member' => $member, 'project' => $project] = $this->sharedProject();

        $this->actingAs($member)
            ->delete(route('projects.destroy', $project))
            ->assertForbidden();

        $this->assertDatabaseHas('projects', ['id' => $project->id]);
    }

    public function test_deleting_project_cascades_tasks(): void
    {
        ['owner' => $owner, 'project' => $project] = $this->ownedProject();
        Task::factory()->for($project)->count(2)->create();

        $this->actingAs($owner)->delete(route('projects.destroy', $project));

        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_mass_assignment_cannot_set_user_id_on_project_create(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($user)
            ->post(route('projects.store'), [
                'name' => 'Ownership test',
                'description' => null,
                'user_id' => $other->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('projects', [
            'name' => 'Ownership test',
            'user_id' => $user->id,
        ]);
    }
}
