<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UiPolishTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_pages_do_not_show_stale_placeholder_navigation_or_copy(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertDontSee('Coming soon', false);
        $response->assertDontSee('foundation release', false);
        $response->assertDontSee('learning stage', false);
        $response->assertDontSee('>Tasks</', false);
        $response->assertDontSee('>Team</', false);
    }

    public function test_authenticated_layout_keeps_core_navigation_without_global_tasks_or_team(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Notifications', false);
        $response->assertDontSee('Coming in a later stage', false);
        $response->assertDontSee('>Tasks</', false);
        $response->assertDontSee('>Team</', false);
    }

    public function test_project_show_empty_tasks_offers_create_task_action(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $response = $this->actingAs($user)->get(route('projects.show', $project));

        $response->assertOk();
        $response->assertSee('No tasks yet', false);
        $response->assertSee('Create task', false);
        $response->assertSee(route('projects.tasks.create', $project), false);
    }

    public function test_flash_status_banner_exposes_accessible_status_role(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('projects.store'), [
            'name' => 'Flash banner project',
            'description' => null,
        ]);

        $response->assertRedirect();
        $followUp = $this->actingAs($user)->get($response->headers->get('Location'));

        $followUp->assertSee('role="status"', false);
        $followUp->assertSee('Project created successfully', false);
    }
}
