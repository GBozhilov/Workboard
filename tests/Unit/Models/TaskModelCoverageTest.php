<?php

namespace Tests\Unit\Models;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Comment;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesWorkBoardFixtures;
use Tests\TestCase;

class TaskModelCoverageTest extends TestCase
{
    use CreatesWorkBoardFixtures;
    use RefreshDatabase;

    public function test_task_belongs_to_project(): void
    {
        ['project' => $project, 'task' => $task] = $this->ownedTask();

        $this->assertTrue($task->project->is($project));
    }

    public function test_task_has_comments_relationship(): void
    {
        ['owner' => $owner, 'task' => $task] = $this->ownedTask();
        Comment::factory()->for($task)->for($owner)->count(2)->create();

        $this->assertCount(2, $task->comments);
    }

    public function test_task_assignee_relationship(): void
    {
        ['project' => $project] = $this->ownedProject();
        $assignee = User::factory()->create();
        $task = Task::factory()->for($project)->create(['assigned_to' => $assignee->id]);

        $this->assertTrue($task->assignee->is($assignee));
    }

    public function test_task_due_date_is_cast_to_date(): void
    {
        ['task' => $task] = $this->ownedTask(['due_date' => '2026-01-15']);

        $this->assertSame('2026-01-15', $task->due_date->toDateString());
    }

    public function test_task_search_scope_matches_title_fragment(): void
    {
        ['project' => $project] = $this->ownedProject();
        Task::factory()->for($project)->create(['title' => 'Unique searchable title']);

        $results = Task::query()->search('searchable')->get();

        $this->assertCount(1, $results);
    }

    public function test_task_filter_status_scope_limits_results(): void
    {
        ['project' => $project] = $this->ownedProject();
        Task::factory()->for($project)->todo()->create();
        Task::factory()->for($project)->completed()->create();

        $todoCount = Task::query()->filterStatus(TaskStatus::Todo->value)->count();

        $this->assertSame(1, $todoCount);
    }

    public function test_task_filter_priority_scope_limits_results(): void
    {
        ['project' => $project] = $this->ownedProject();
        Task::factory()->for($project)->create(['priority' => TaskPriority::Low]);
        Task::factory()->for($project)->create(['priority' => TaskPriority::High]);

        $highCount = Task::query()->filterPriority(TaskPriority::High->value)->count();

        $this->assertSame(1, $highCount);
    }
}
