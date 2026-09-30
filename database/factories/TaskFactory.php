<?php

namespace Database\Factories;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    protected $model = Task::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'assigned_to' => null,
            'title' => ucfirst(fake()->words(random_int(3, 6), true)),
            'description' => fake()->optional(0.8)->paragraph(),
            'status' => fake()->randomElement(TaskStatus::cases()),
            'priority' => fake()->randomElement(TaskPriority::cases()),
            'due_date' => fake()->optional(0.7)->dateTimeBetween('-2 weeks', '+2 months')?->format('Y-m-d'),
        ];
    }

    public function assignedTo(User $user): static
    {
        return $this->state(fn () => ['assigned_to' => $user->id]);
    }

    public function unassigned(): static
    {
        return $this->state(fn () => ['assigned_to' => null]);
    }

    public function todo(): static
    {
        return $this->state(fn () => ['status' => TaskStatus::Todo]);
    }

    public function inProgress(): static
    {
        return $this->state(fn () => ['status' => TaskStatus::InProgress]);
    }

    public function completed(): static
    {
        return $this->state(fn () => ['status' => TaskStatus::Done]);
    }
}
