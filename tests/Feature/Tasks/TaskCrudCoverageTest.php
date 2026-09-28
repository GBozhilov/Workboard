<?php

namespace Tests\Feature\Tasks;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesWorkBoardFixtures;
use Tests\TestCase;

class TaskCrudCoverageTest extends TestCase
{
    use CreatesWorkBoardFixtures;
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validTaskPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Coverage task',
            'description' => null,
            'status' => TaskStatus::Todo->value,
            'priority' => TaskPriority::Medium->value,
            'due_date' => null,
        ], $overrides);
    }

    public function test_guest_cannot_create_task(): void
    {
        ['project' => $project] = $this->ownedProject();

        $this->post(route('projects.tasks.store', $project), $this->validTaskPayload())
            ->assertRedirect(route('login'));
    }

    public function test_member_can_create_task_in_shared_project(): void
    {
        ['member' => $member, 'project' => $project] = $this->sharedProject();

        $this->actingAs($member)
            ->post(route('projects.tasks.store', $project), $this->validTaskPayload(['title' => 'Member task']))
            ->assertRedirect();

        $this->assertDatabaseHas('tasks', ['project_id' => $project->id, 'title' => 'Member task']);
    }

    public function test_outsider_cannot_create_task(): void
    {
        ['project' => $project] = $this->ownedProject();
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->post(route('projects.tasks.store', $project), $this->validTaskPayload())
            ->assertForbidden();
    }

    public function test_task_create_rejects_missing_title(): void
    {
        ['owner' => $owner, 'project' => $project] = $this->ownedProject();

        $this->actingAs($owner)
            ->from(route('projects.tasks.create', $project))
            ->post(route('projects.tasks.store', $project), $this->validTaskPayload(['title' => '']))
            ->assertSessionHasErrors('title');
    }

    public function test_task_create_accepts_title_at_max_length(): void
    {
        ['owner' => $owner, 'project' => $project] = $this->ownedProject();
        $title = str_repeat('t', 255);

        $this->actingAs($owner)
            ->post(route('projects.tasks.store', $project), $this->validTaskPayload(['title' => $title]))
            ->assertRedirect();

        $this->assertDatabaseHas('tasks', ['title' => $title]);
    }

    public function test_task_create_rejects_title_over_max_length(): void
    {
        ['owner' => $owner, 'project' => $project] = $this->ownedProject();

        $this->actingAs($owner)
            ->from(route('projects.tasks.create', $project))
            ->post(route('projects.tasks.store', $project), $this->validTaskPayload([
                'title' => str_repeat('t', 256),
            ]))
            ->assertSessionHasErrors('title');
    }

    public function test_task_create_rejects_description_over_max_length(): void
    {
        ['owner' => $owner, 'project' => $project] = $this->ownedProject();

        $this->actingAs($owner)
            ->from(route('projects.tasks.create', $project))
            ->post(route('projects.tasks.store', $project), $this->validTaskPayload([
                'description' => str_repeat('d', 5001),
            ]))
            ->assertSessionHasErrors('description');
    }

    /**
     * @return array<string, array{0: TaskStatus}>
     */
    public static function taskStatusProvider(): array
    {
        return [
            'todo' => [TaskStatus::Todo],
            'in_progress' => [TaskStatus::InProgress],
            'done' => [TaskStatus::Done],
        ];
    }

    #[DataProvider('taskStatusProvider')]
    public function test_task_create_accepts_each_valid_status(TaskStatus $status): void
    {
        ['owner' => $owner, 'project' => $project] = $this->ownedProject();

        $this->actingAs($owner)
            ->post(route('projects.tasks.store', $project), $this->validTaskPayload([
                'title' => 'Status '.$status->value,
                'status' => $status->value,
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('tasks', [
            'project_id' => $project->id,
            'status' => $status->value,
        ]);
    }

    /**
     * @return array<string, array{0: TaskPriority}>
     */
    public static function taskPriorityProvider(): array
    {
        return [
            'low' => [TaskPriority::Low],
            'medium' => [TaskPriority::Medium],
            'high' => [TaskPriority::High],
        ];
    }

    #[DataProvider('taskPriorityProvider')]
    public function test_task_create_accepts_each_valid_priority(TaskPriority $priority): void
    {
        ['owner' => $owner, 'project' => $project] = $this->ownedProject();

        $this->actingAs($owner)
            ->post(route('projects.tasks.store', $project), $this->validTaskPayload([
                'title' => 'Priority '.$priority->value,
                'priority' => $priority->value,
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('tasks', [
            'project_id' => $project->id,
            'priority' => $priority->value,
        ]);
    }

    public function test_task_create_accepts_future_due_date(): void
    {
        ['owner' => $owner, 'project' => $project] = $this->ownedProject();

        $this->actingAs($owner)
            ->post(route('projects.tasks.store', $project), $this->validTaskPayload([
                'due_date' => now()->addWeek()->toDateString(),
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('tasks', ['project_id' => $project->id]);
    }

    public function test_task_create_accepts_past_due_date(): void
    {
        ['owner' => $owner, 'project' => $project] = $this->ownedProject();

        $this->actingAs($owner)
            ->post(route('projects.tasks.store', $project), $this->validTaskPayload([
                'due_date' => now()->subWeek()->toDateString(),
            ]))
            ->assertRedirect();
    }

    public function test_member_can_update_task_fields(): void
    {
        ['member' => $member, 'project' => $project] = $this->sharedProject();
        $task = Task::factory()->for($project)->create(['title' => 'Before']);

        $this->actingAs($member)
            ->put(route('projects.tasks.update', [$project, $task]), $this->validTaskPayload([
                'title' => 'After',
                'status' => TaskStatus::InProgress->value,
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'title' => 'After']);
    }

    public function test_update_can_clear_task_description(): void
    {
        ['owner' => $owner, 'project' => $project] = $this->ownedProject();
        $task = Task::factory()->for($project)->create(['description' => 'Remove me']);

        $this->actingAs($owner)
            ->put(route('projects.tasks.update', [$project, $task]), $this->validTaskPayload([
                'title' => $task->title,
                'description' => null,
            ]))
            ->assertRedirect();

        $task->refresh();
        $this->assertNull($task->description);
    }

    public function test_member_cannot_delete_task(): void
    {
        ['member' => $member, 'project' => $project] = $this->sharedProject();
        $task = Task::factory()->for($project)->create();

        $this->actingAs($member)
            ->delete(route('projects.tasks.destroy', [$project, $task]))
            ->assertForbidden();

        $this->assertDatabaseHas('tasks', ['id' => $task->id]);
    }

    public function test_owner_can_delete_task(): void
    {
        ['owner' => $owner, 'project' => $project] = $this->ownedProject();
        $task = Task::factory()->for($project)->create();

        $this->actingAs($owner)
            ->delete(route('projects.tasks.destroy', [$project, $task]))
            ->assertRedirect();

        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }

    public function test_nested_task_show_returns_not_found_for_wrong_project(): void
    {
        $owner = User::factory()->create();
        $projectA = Project::factory()->for($owner)->create();
        $projectB = Project::factory()->for($owner)->create();
        $taskOnB = Task::factory()->for($projectB)->create();

        $this->actingAs($owner)
            ->get(route('projects.tasks.show', [$projectA, $taskOnB]))
            ->assertNotFound();
    }
}
