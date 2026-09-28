<?php

namespace Tests\Feature\Routes;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesWorkBoardFixtures;
use Tests\TestCase;

class NamedRouteCoverageTest extends TestCase
{
    use CreatesWorkBoardFixtures;
    use RefreshDatabase;

    public function test_dashboard_route_requires_authentication(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_projects_edit_route_requires_authentication(): void
    {
        ['project' => $project] = $this->ownedProject();

        $this->get(route('projects.edit', $project))->assertRedirect(route('login'));
    }

    public function test_projects_show_route_requires_authentication(): void
    {
        ['project' => $project] = $this->ownedProject();

        $this->get(route('projects.show', $project))->assertRedirect(route('login'));
    }

    public function test_task_create_route_is_reachable_for_project_owner(): void
    {
        ['owner' => $owner, 'project' => $project] = $this->ownedProject();

        $this->actingAs($owner)
            ->get(route('projects.tasks.create', $project))
            ->assertOk();
    }

    public function test_task_edit_route_is_reachable_for_project_owner(): void
    {
        ['owner' => $owner, 'project' => $project, 'task' => $task] = $this->ownedTask();

        $this->actingAs($owner)
            ->get(route('projects.tasks.edit', [$project, $task]))
            ->assertOk();
    }

    public function test_authenticated_user_can_open_projects_index(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('projects.index'))
            ->assertOk();
    }

    public function test_authenticated_user_can_open_project_create_form(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('projects.create'))
            ->assertOk();
    }

    public function test_nonexistent_project_show_returns_not_found(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('projects.show', ['project' => 999999]))
            ->assertNotFound();
    }

    public function test_nonexistent_task_show_returns_not_found(): void
    {
        ['owner' => $owner, 'project' => $project] = $this->ownedProject();

        $this->actingAs($owner)
            ->get(route('projects.tasks.show', [$project, 999999]))
            ->assertNotFound();
    }
}
