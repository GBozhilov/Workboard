<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_project_pages(): void
    {
        $project = Project::factory()->create();

        $this->get(route('projects.index'))->assertRedirect(route('login'));
        $this->get(route('projects.create'))->assertRedirect(route('login'));
        $this->post(route('projects.store'), ['name' => 'Test'])->assertRedirect(route('login'));
        $this->get(route('projects.show', $project))->assertRedirect(route('login'));
        $this->get(route('projects.edit', $project))->assertRedirect(route('login'));
        $this->put(route('projects.update', $project), ['name' => 'Hacked'])->assertRedirect(route('login'));
        $this->delete(route('projects.destroy', $project))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_their_project_list(): void
    {
        $user = User::factory()->create();
        Project::factory()->for($user)->create(['name' => 'My Board']);
        Project::factory()->create(['name' => 'Other User Secret Project']);

        $response = $this->actingAs($user)->get(route('projects.index'));

        $response->assertOk();
        $response->assertSee('My Board');
        $response->assertDontSee('Other User Secret Project');
    }

    public function test_projects_index_links_each_card_to_project_show(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['name' => 'Open Me Board']);

        $this->actingAs($user)
            ->get(route('projects.index'))
            ->assertOk()
            ->assertSee(route('projects.show', $project), false)
            ->assertSee('Open Me Board');
    }

    public function test_authenticated_user_can_open_own_project_from_show_route(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['name' => 'Detail Page Project']);

        $this->actingAs($user)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('Detail Page Project')
            ->assertSee('Tasks');
    }

    public function test_user_can_create_a_project(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('projects.store'), [
            'name' => 'WorkBoard Alpha',
            'description' => 'First learning project',
        ]);

        $project = Project::where('name', 'WorkBoard Alpha')->first();

        $response->assertRedirect(route('projects.show', $project));
        $this->assertDatabaseHas('projects', [
            'name' => 'WorkBoard Alpha',
            'user_id' => $user->id,
        ]);
    }

    public function test_validation_prevents_invalid_project_creation(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->from(route('projects.create'))->post(route('projects.store'), [
            'name' => '',
        ]);

        $response->assertRedirect(route('projects.create'));
        $response->assertSessionHasErrors('name');
        $this->assertDatabaseCount('projects', 0);
    }

    public function test_user_can_view_their_own_project(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $this->actingAs($user)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee($project->name);
    }

    public function test_user_can_edit_their_own_project(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $this->actingAs($user)
            ->get(route('projects.edit', $project))
            ->assertOk()
            ->assertSee('Edit project');
    }

    public function test_user_can_update_their_own_project(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['name' => 'Old name']);

        $response = $this->actingAs($user)->put(route('projects.update', $project), [
            'name' => 'New name',
            'description' => 'Updated description',
        ]);

        $response->assertRedirect(route('projects.show', $project));
        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'name' => 'New name',
            'user_id' => $user->id,
        ]);
    }

    public function test_user_can_delete_their_own_project(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $response = $this->actingAs($user)->delete(route('projects.destroy', $project));

        $response->assertRedirect(route('projects.index'));
        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
    }

    public function test_user_cannot_view_another_users_project(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $project = Project::factory()->for($owner)->create();

        $this->actingAs($intruder)
            ->get(route('projects.show', $project))
            ->assertForbidden();
    }

    public function test_user_cannot_edit_another_users_project(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $project = Project::factory()->for($owner)->create();

        $this->actingAs($intruder)
            ->get(route('projects.edit', $project))
            ->assertForbidden();
    }

    public function test_user_cannot_update_another_users_project(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $project = Project::factory()->for($owner)->create(['name' => 'Original']);

        $this->actingAs($intruder)
            ->put(route('projects.update', $project), [
                'name' => 'Stolen',
                'description' => null,
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'name' => 'Original',
        ]);
    }

    public function test_user_cannot_delete_another_users_project(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $project = Project::factory()->for($owner)->create();

        $this->actingAs($intruder)
            ->delete(route('projects.destroy', $project))
            ->assertForbidden();

        $this->assertDatabaseHas('projects', ['id' => $project->id]);
    }
}
