<?php

namespace Tests\Unit\Enums;

use App\Enums\TaskPriority;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use ValueError;

class TaskPriorityCoverageTest extends TestCase
{
    public function test_task_priority_has_three_cases(): void
    {
        $this->assertCount(3, TaskPriority::cases());
    }

    /**
     * @return array<string, array{0: TaskPriority}>
     */
    public static function priorityCaseProvider(): array
    {
        return [
            'low' => [TaskPriority::Low],
            'medium' => [TaskPriority::Medium],
            'high' => [TaskPriority::High],
        ];
    }

    /**
     * @return array<string, array{0: TaskPriority, 1: string}>
     */
    public static function priorityValueProvider(): array
    {
        return [
            'low' => [TaskPriority::Low, 'low'],
            'medium' => [TaskPriority::Medium, 'medium'],
            'high' => [TaskPriority::High, 'high'],
        ];
    }

    #[DataProvider('priorityValueProvider')]
    public function test_task_priority_backing_values(TaskPriority $priority, string $value): void
    {
        $this->assertSame($value, $priority->value);
    }

    #[DataProvider('priorityValueProvider')]
    public function test_task_priority_from_accepts_value(TaskPriority $priority, string $value): void
    {
        $this->assertSame($priority, TaskPriority::from($value));
    }

    #[DataProvider('priorityValueProvider')]
    public function test_task_priority_try_from_accepts_value(TaskPriority $priority, string $value): void
    {
        $this->assertSame($priority, TaskPriority::tryFrom($value));
    }

    #[DataProvider('priorityCaseProvider')]
    public function test_task_priority_labels_are_non_empty(TaskPriority $priority): void
    {
        $this->assertNotSame('', $priority->label());
    }

    public function test_task_priority_low_label(): void
    {
        $this->assertSame('Low', TaskPriority::Low->label());
    }

    public function test_task_priority_medium_label(): void
    {
        $this->assertSame('Medium', TaskPriority::Medium->label());
    }

    public function test_task_priority_high_label(): void
    {
        $this->assertSame('High', TaskPriority::High->label());
    }

    #[DataProvider('invalidPriorityProvider')]
    public function test_task_priority_try_from_rejects_invalid_value(string $value): void
    {
        $this->assertNull(TaskPriority::tryFrom($value));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function invalidPriorityProvider(): array
    {
        return [
            'empty' => [''],
            'urgent' => ['urgent'],
            'uppercase' => ['HIGH'],
        ];
    }

    public function test_task_priority_from_throws_on_invalid_value(): void
    {
        $this->expectException(ValueError::class);

        TaskPriority::from('invalid');
    }
}
