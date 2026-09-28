<?php

namespace Tests\Feature\Security;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Comment;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesWorkBoardFixtures;
use Tests\TestCase;

class SecurityRegressionCoverageTest extends TestCase
{
    use CreatesWorkBoardFixtures;
    use RefreshDatabase;

    public function test_outsider_cannot_update_foreign_project(): void
    {
        ['project' => $project] = $this->ownedProject();
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->put(route('projects.update', $project), ['name' => 'Hijacked', 'description' => null])
            ->assertForbidden();
    }

    public function test_outsider_cannot_delete_foreign_project(): void
    {
        ['project' => $project] = $this->ownedProject();
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->delete(route('projects.destroy', $project))
            ->assertForbidden();
    }

    public function test_outsider_cannot_update_foreign_task(): void
    {
        ['project' => $project, 'task' => $task] = $this->ownedTask();
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->put(route('projects.tasks.update', [$project, $task]), [
                'title' => 'Hacked',
                'description' => null,
                'status' => TaskStatus::Todo->value,
                'priority' => TaskPriority::Medium->value,
                'due_date' => null,
            ])
            ->assertForbidden();
    }

    public function test_member_cannot_delete_task_even_with_direct_request(): void
    {
        ['member' => $member, 'project' => $project] = $this->sharedProject();
        $task = Task::factory()->for($project)->create();

        $this->actingAs($member)
            ->delete(route('projects.tasks.destroy', [$project, $task]))
            ->assertForbidden();
    }

    public function test_guest_cannot_access_projects_index(): void
    {
        $this->get(route('projects.index'))->assertRedirect(route('login'));
    }

    public function test_guest_cannot_access_project_create_form(): void
    {
        $this->get(route('projects.create'))->assertRedirect(route('login'));
    }

    public function test_invalid_project_sort_query_does_not_break_index(): void
    {
        $user = User::factory()->create();
        Project::factory()->for($user)->create(['name' => 'Still safe']);

        $this->actingAs($user)
            ->get(route('projects.index', ['sort' => "'; DROP TABLE projects; --"]))
            ->assertOk()
            ->assertSee('Still safe', false);
    }

    public function test_invalid_task_sort_query_does_not_break_project_show(): void
    {
        ['owner' => $owner, 'project' => $project] = $this->ownedProject();
        Task::factory()->for($project)->create(['title' => 'Still listed']);

        $this->actingAs($owner)
            ->get(route('projects.show', ['project' => $project, 'sort' => 'unsafe-sort']))
            ->assertOk()
            ->assertSee('Still listed', false);
    }

    public function test_invalid_status_cannot_be_persisted_through_task_create(): void
    {
        ['owner' => $owner, 'project' => $project] = $this->ownedProject();

        $this->actingAs($owner)
            ->from(route('projects.tasks.create', $project))
            ->post(route('projects.tasks.store', $project), [
                'title' => 'Bad status',
                'description' => null,
                'status' => 'archived',
                'priority' => TaskPriority::Medium->value,
                'due_date' => null,
            ])
            ->assertSessionHasErrors('status');

        $this->assertDatabaseMissing('tasks', ['title' => 'Bad status']);
    }

    public function test_comment_on_wrong_task_url_returns_not_found(): void
    {
        ['owner' => $owner, 'project' => $project] = $this->ownedProject();
        $taskA = Task::factory()->for($project)->create();
        $taskB = Task::factory()->for($project)->create();
        $commentOnB = Comment::factory()->for($taskB)->for($owner)->create();

        $this->actingAs($owner)
            ->delete(route('projects.tasks.comments.destroy', [$project, $taskA, $commentOnB]))
            ->assertNotFound();
    }
}
