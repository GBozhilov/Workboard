<?php

namespace Tests\Unit\Enums;

use App\Enums\TaskStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use ValueError;

class TaskStatusCoverageTest extends TestCase
{
    public function test_task_status_has_three_cases(): void
    {
        $this->assertCount(3, TaskStatus::cases());
    }

    /**
     * @return array<string, array{0: TaskStatus}>
     */
    public static function statusCaseProvider(): array
    {
        return [
            'todo' => [TaskStatus::Todo],
            'in_progress' => [TaskStatus::InProgress],
            'done' => [TaskStatus::Done],
        ];
    }

    /**
     * @return array<string, array{0: TaskStatus, 1: string}>
     */
    public static function statusValueProvider(): array
    {
        return [
            'todo' => [TaskStatus::Todo, 'todo'],
            'in_progress' => [TaskStatus::InProgress, 'in_progress'],
            'done' => [TaskStatus::Done, 'done'],
        ];
    }

    #[DataProvider('statusValueProvider')]
    public function test_task_status_backing_values(TaskStatus $status, string $value): void
    {
        $this->assertSame($value, $status->value);
    }

    #[DataProvider('statusValueProvider')]
    public function test_task_status_from_accepts_value(TaskStatus $status, string $value): void
    {
        $this->assertSame($status, TaskStatus::from($value));
    }

    #[DataProvider('statusValueProvider')]
    public function test_task_status_try_from_accepts_value(TaskStatus $status, string $value): void
    {
        $this->assertSame($status, TaskStatus::tryFrom($value));
    }

    #[DataProvider('statusCaseProvider')]
    public function test_task_status_labels_are_non_empty(TaskStatus $status): void
    {
        $this->assertNotSame('', $status->label());
    }

    public function test_task_status_todo_label(): void
    {
        $this->assertSame('To do', TaskStatus::Todo->label());
    }

    public function test_task_status_in_progress_label(): void
    {
        $this->assertSame('In progress', TaskStatus::InProgress->label());
    }

    public function test_task_status_done_label(): void
    {
        $this->assertSame('Done', TaskStatus::Done->label());
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function invalidStatusProvider(): array
    {
        return [
            'empty' => [''],
            'unknown' => ['archived'],
            'uppercase' => ['TODO'],
        ];
    }

    #[DataProvider('invalidStatusProvider')]
    public function test_task_status_try_from_rejects_invalid_value(string $value): void
    {
        $this->assertNull(TaskStatus::tryFrom($value));
    }

    public function test_task_status_from_throws_on_invalid_value(): void
    {
        $this->expectException(ValueError::class);

        TaskStatus::from('invalid');
    }
}
