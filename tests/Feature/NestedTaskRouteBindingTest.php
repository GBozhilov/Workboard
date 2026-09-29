<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Tag;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NestedTaskRouteBindingTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_task_in_project_can_be_viewed_with_nested_show_route(): void
    {
        $user = User::factory()->create();
        $projectA = Project::factory()->for($user)->create();

        $taskA1 = Task::factory()->for($projectA)->todo()->create(['title' => 'Nested A1']);
        $taskA2 = Task::factory()->for($projectA)->todo()->create([
            'title' => 'Nested A2',
            'due_date' => now()->addWeek(),
        ]);
        $taskA3 = Task::factory()->for($projectA)->todo()->create(['title' => 'Nested A3']);

        $this->assertNotSame($taskA1->getKey(), $taskA2->getKey());
        $this->assertNotSame($taskA2->getKey(), $taskA3->getKey());
        $this->assertNotSame($projectA->getKey(), $taskA3->getKey());

        $this->actingAs($user)
            ->get(route('projects.tasks.show', ['project' => $projectA->getKey(), 'task' => $taskA1->getKey()]))
            ->assertOk()
            ->assertSee('Nested A1', false);

        $this->actingAs($user)
            ->get(route('projects.tasks.show', ['project' => $projectA->getKey(), 'task' => $taskA2->getKey()]))
            ->assertOk()
            ->assertSee('Nested A2', false);

        $this->actingAs($user)
            ->get(route('projects.tasks.show', ['project' => $projectA->getKey(), 'task' => $taskA3->getKey()]))
            ->assertOk()
            ->assertSee('Nested A3', false);
    }

    public function test_nested_show_returns_not_found_when_task_belongs_to_another_project(): void
    {
        $user = User::factory()->create();
        $projectA = Project::factory()->for($user)->create();
        $projectB = Project::factory()->for($user)->create();
        $taskB1 = Task::factory()->for($projectB)->create(['title' => 'Only on B']);

        $this->actingAs($user)
            ->get(route('projects.tasks.show', ['project' => $projectA->getKey(), 'task' => $taskB1->getKey()]))
            ->assertNotFound();
    }

    public function test_tagged_task_show_route_resolves_with_numeric_route_keys(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $plain = Task::factory()->for($project)->todo()->create(['title' => 'Untagged nested']);
        $tagged = Task::factory()->for($project)->todo()->create(['title' => 'Tagged nested']);
        $tag = Tag::factory()->for($project)->create(['name' => 'urgent']);
        $tagged->tags()->attach($tag->id);

        $this->actingAs($user)
            ->get(route('projects.tasks.show', ['project' => $project->getKey(), 'task' => $plain->getKey()]))
            ->assertOk()
            ->assertSee('Untagged nested', false);

        $this->actingAs($user)
            ->get(route('projects.tasks.show', ['project' => $project->getKey(), 'task' => $tagged->getKey()]))
            ->assertOk()
            ->assertSee('Tagged nested', false)
            ->assertSee('urgent', false);
    }

    public function test_nested_task_edit_update_and_destroy_routes_resolve_for_valid_pairs(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($project)->todo()->create(['title' => 'Mutable nested']);

        $this->actingAs($user)
            ->get(route('projects.tasks.edit', ['project' => $project->getKey(), 'task' => $task->getKey()]))
            ->assertOk()
            ->assertSee('Mutable nested', false);

        $this->actingAs($user)
            ->put(route('projects.tasks.update', ['project' => $project->getKey(), 'task' => $task->getKey()]), [
                'title' => 'Updated nested',
                'description' => null,
                'status' => $task->status->value,
                'priority' => $task->priority->value,
                'due_date' => null,
            ])
            ->assertRedirect(route('projects.tasks.show', ['project' => $project->getKey(), 'task' => $task->getKey()]));

        $this->actingAs($user)
            ->delete(route('projects.tasks.destroy', ['project' => $project->getKey(), 'task' => $task->getKey()]))
            ->assertRedirect(route('projects.show', $project));

        $this->assertDatabaseMissing('tasks', ['id' => $task->getKey()]);
    }
}
