<?php

namespace Database\Factories;

use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskAttachment>
 */
class TaskAttachmentFactory extends Factory
{
    protected $model = TaskAttachment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'task_id' => Task::factory(),
            'user_id' => User::factory(),
            'original_name' => 'document.pdf',
            'path' => 'task-attachments/1/1/'.fake()->uuid().'.pdf',
            'mime_type' => 'application/pdf',
            'size' => fake()->numberBetween(500, 500_000),
        ];
    }
}
