<?php

namespace Tests\Feature;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectTaskListingTest extends TestCase
{
    use RefreshDatabase;

    public function test_task_search_filters_by_title(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        Task::factory()->for($project)->create(['title' => 'Deploy release']);
        Task::factory()->for($project)->create(['title' => 'Write docs']);

        $this->actingAs($user)
            ->get(route('projects.show', [$project, 'search' => 'Deploy']))
            ->assertOk()
            ->assertSee('Deploy release')
            ->assertDontSee('Write docs');
    }

    public function test_task_status_filter(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        Task::factory()->for($project)->todo()->create(['title' => 'Todo item']);
        Task::factory()->for($project)->completed()->create(['title' => 'Done item']);

        $this->actingAs($user)
            ->get(route('projects.show', [$project, 'status' => TaskStatus::Done->value]))
            ->assertOk()
            ->assertSee('Done item')
            ->assertDontSee('Todo item');
    }

    public function test_task_priority_filter(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        Task::factory()->for($project)->create(['title' => 'Low task', 'priority' => TaskPriority::Low]);
        Task::factory()->for($project)->create(['title' => 'High task', 'priority' => TaskPriority::High]);

        $this->actingAs($user)
            ->get(route('projects.show', [$project, 'priority' => TaskPriority::High->value]))
            ->assertOk()
            ->assertSee('High task')
            ->assertDontSee('Low task');
    }

    public function test_task_sorting_and_combined_filters(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        Task::factory()->for($project)->todo()->create([
            'title' => 'Alpha todo',
            'priority' => TaskPriority::Low,
        ]);
        Task::factory()->for($project)->completed()->create([
            'title' => 'Beta done',
            'priority' => TaskPriority::High,
        ]);

        $this->actingAs($user)
            ->get(route('projects.show', [
                'project' => $project,
                'status' => TaskStatus::Todo->value,
                'sort' => 'title_asc',
            ]))
            ->assertOk()
            ->assertSee('Alpha todo')
            ->assertDontSee('Beta done');
    }

    public function test_task_pagination_preserves_filters_in_links(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        foreach (range(1, 11) as $index) {
            Task::factory()->for($project)->todo()->create([
                'title' => "Listed Task {$index}",
                'created_at' => now()->subMinutes(11 - $index),
            ]);
        }

        $this->actingAs($user)
            ->get(route('projects.show', ['project' => $project, 'status' => TaskStatus::Todo->value, 'sort' => 'oldest']))
            ->assertOk()
            ->assertSee('Listed Task 1')
            ->assertDontSee('Listed Task 11');

        $response = $this->actingAs($user)->get(route('projects.show', [
            'project' => $project,
            'status' => TaskStatus::Todo->value,
            'sort' => 'oldest',
            'page' => 2,
        ]));

        $response->assertOk()->assertSee('Listed Task 11');
        $this->assertStringContainsString('status=todo', $response->getContent());
        $this->assertStringContainsString('sort=oldest', $response->getContent());
    }

    public function test_invalid_task_filters_fall_back_without_error(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        Task::factory()->for($project)->create(['title' => 'Still visible']);

        $this->actingAs($user)
            ->get(route('projects.show', [
                'project' => $project,
                'status' => 'bogus',
                'priority' => 'urgent',
                'sort' => 'drop-table',
            ]))
            ->assertOk()
            ->assertSee('Still visible');
    }

    public function test_outsider_cannot_access_filtered_project_tasks(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        Task::factory()->for($project)->create(['title' => 'Secret task']);

        $this->actingAs($outsider)
            ->get(route('projects.show', ['project' => $project, 'search' => 'Secret']))
            ->assertForbidden();
    }
}
