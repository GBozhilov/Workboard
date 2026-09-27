<?php

namespace Tests\Unit;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_task_casts_status_and_priority_to_enums(): void
    {
        $task = Task::factory()->create([
            'status' => TaskStatus::InProgress,
            'priority' => TaskPriority::High,
        ]);

        $task->refresh();

        $this->assertInstanceOf(TaskStatus::class, $task->status);
        $this->assertInstanceOf(TaskPriority::class, $task->priority);
        $this->assertSame(TaskStatus::InProgress, $task->status);
        $this->assertSame(TaskPriority::High, $task->priority);
    }
}
