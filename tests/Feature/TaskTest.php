<?php

namespace Tests\Feature;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validTaskPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Ship feature',
            'description' => 'Implement task board',
            'status' => TaskStatus::Todo->value,
            'priority' => TaskPriority::Medium->value,
            'due_date' => null,
        ], $overrides);
    }

    public function test_guests_cannot_access_task_management(): void
    {
        $project = Project::factory()->create();
        $task = Task::factory()->for($project)->create();

        $this->get(route('projects.tasks.create', $project))->assertRedirect(route('login'));
        $this->post(route('projects.tasks.store', $project), $this->validTaskPayload())->assertRedirect(route('login'));
        $this->get(route('projects.tasks.show', [$project, $task]))->assertRedirect(route('login'));
        $this->get(route('projects.tasks.edit', [$project, $task]))->assertRedirect(route('login'));
        $this->put(route('projects.tasks.update', [$project, $task]), $this->validTaskPayload())->assertRedirect(route('login'));
        $this->delete(route('projects.tasks.destroy', [$project, $task]))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_create_task_page(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['name' => 'Alpha Board']);

        $this->actingAs($user)
            ->get(route('projects.tasks.create', $project))
            ->assertOk()
            ->assertSee('Create task', false)
            ->assertSee('Alpha Board', false)
            ->assertSee('name="title"', false)
            ->assertSee('name="status"', false)
            ->assertSee('name="priority"', false)
            ->assertSee('Save task', false);
    }

    public function test_authenticated_user_can_create_a_task_in_their_own_project(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $response = $this->actingAs($user)->post(
            route('projects.tasks.store', $project),
            $this->validTaskPayload(['title' => 'Write tests'])
        );

        $task = Task::where('title', 'Write tests')->first();

        $response->assertRedirect(route('projects.tasks.show', [$project, $task]));
        $this->assertDatabaseHas('tasks', [
            'title' => 'Write tests',
            'project_id' => $project->id,
        ]);
    }

    public function test_invalid_task_data_is_rejected(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $response = $this->actingAs($user)->from(route('projects.tasks.create', $project))->post(
            route('projects.tasks.store', $project),
            $this->validTaskPayload(['title' => ''])
        );

        $response->assertRedirect(route('projects.tasks.create', $project));
        $response->assertSessionHasErrors('title');
        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_task_belongs_to_the_correct_project(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $this->actingAs($user)->post(
            route('projects.tasks.store', $project),
            $this->validTaskPayload()
        );

        $this->assertDatabaseHas('tasks', [
            'project_id' => $project->id,
        ]);
    }

    public function test_user_can_view_their_own_task(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($project)->create(['title' => 'Visible task']);

        $this->actingAs($user)
            ->get(route('projects.tasks.show', [$project, $task]))
            ->assertOk()
            ->assertSee('Visible task');
    }

    public function test_user_can_edit_and_update_their_own_task(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($project)->todo()->create(['title' => 'Old title']);

        $this->actingAs($user)
            ->get(route('projects.tasks.edit', [$project, $task]))
            ->assertOk()
            ->assertSee('Edit task');

        $response = $this->actingAs($user)->put(
            route('projects.tasks.update', [$project, $task]),
            $this->validTaskPayload(['title' => 'Updated title'])
        );

        $response->assertRedirect(route('projects.tasks.show', [$project, $task]));
        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'title' => 'Updated title',
        ]);
    }

    public function test_user_can_change_task_status(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($project)->todo()->create();

        $this->actingAs($user)->put(
            route('projects.tasks.update', [$project, $task]),
            $this->validTaskPayload(['status' => TaskStatus::Done->value])
        );

        $this->assertSame(TaskStatus::Done, $task->fresh()->status);
    }

    public function test_user_can_change_task_priority(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($project)->create();

        $this->actingAs($user)->put(
            route('projects.tasks.update', [$project, $task]),
            $this->validTaskPayload(['priority' => TaskPriority::High->value])
        );

        $this->assertSame(TaskPriority::High, $task->fresh()->priority);
    }

    public function test_user_can_delete_their_own_task(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($project)->create();

        $response = $this->actingAs($user)->delete(route('projects.tasks.destroy', [$project, $task]));

        $response->assertRedirect(route('projects.show', $project));
        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }

    public function test_user_cannot_access_tasks_from_another_users_project(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $task = Task::factory()->for($project)->create();

        $this->actingAs($intruder)
            ->get(route('projects.tasks.show', [$project, $task]))
            ->assertForbidden();

        $this->actingAs($intruder)
            ->get(route('projects.tasks.edit', [$project, $task]))
            ->assertForbidden();

        $this->actingAs($intruder)
            ->put(route('projects.tasks.update', [$project, $task]), $this->validTaskPayload())
            ->assertForbidden();

        $this->actingAs($intruder)
            ->delete(route('projects.tasks.destroy', [$project, $task]))
            ->assertForbidden();
    }

    public function test_user_cannot_create_a_task_in_another_users_project(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $project = Project::factory()->for($owner)->create();

        $this->actingAs($intruder)
            ->get(route('projects.tasks.create', $project))
            ->assertForbidden();

        $this->actingAs($intruder)
            ->post(route('projects.tasks.store', $project), $this->validTaskPayload())
            ->assertForbidden();

        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_project_show_lists_tasks_in_tasks_section(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        Task::factory()->for($project)->todo()->create(['title' => 'Design board']);

        $this->actingAs($user)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('Tasks')
            ->assertSee('Create task')
            ->assertSee('Design board')
            ->assertSee('To do');
    }

    public function test_project_show_task_row_links_to_task_show_route(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($project)->todo()->create(['title' => 'Whole row task']);

        $taskShowUrl = route('projects.tasks.show', [$project, $task]);

        $this->actingAs($user)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee($taskShowUrl, false)
            ->assertSee('Whole row task', false);
    }

    public function test_invalid_enum_values_are_rejected(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $response = $this->actingAs($user)->from(route('projects.tasks.create', $project))->post(
            route('projects.tasks.store', $project),
            $this->validTaskPayload([
                'status' => 'not-a-status',
                'priority' => 'urgent',
            ])
        );

        $response->assertRedirect(route('projects.tasks.create', $project));
        $response->assertSessionHasErrors(['status', 'priority']);
    }

    public function test_invalid_due_date_is_rejected(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $response = $this->actingAs($user)->from(route('projects.tasks.create', $project))->post(
            route('projects.tasks.store', $project),
            $this->validTaskPayload(['due_date' => 'not-a-date'])
        );

        $response->assertRedirect(route('projects.tasks.create', $project));
        $response->assertSessionHasErrors('due_date');
        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_nested_task_route_returns_not_found_when_task_belongs_to_another_project(): void
    {
        $user = User::factory()->create();
        $projectA = Project::factory()->for($user)->create();
        $projectB = Project::factory()->for($user)->create();
        $taskOnB = Task::factory()->for($projectB)->create(['title' => 'Wrong nest']);

        $this->actingAs($user)
            ->get(route('projects.tasks.show', [$projectA, $taskOnB]))
            ->assertNotFound();

        $this->actingAs($user)
            ->get(route('projects.tasks.edit', [$projectA, $taskOnB]))
            ->assertNotFound();
    }

    public function test_assigned_to_cannot_be_mass_assigned_through_task_forms(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $this->actingAs($user)->post(
            route('projects.tasks.store', $project),
            $this->validTaskPayload([
                'title' => 'No assignee yet',
                'assigned_to' => $otherUser->id,
            ])
        );

        $this->assertDatabaseHas('tasks', [
            'title' => 'No assignee yet',
            'project_id' => $project->id,
            'assigned_to' => null,
        ]);
    }

}
