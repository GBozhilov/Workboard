<?php

namespace Database\Factories;

use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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

    /**
     * Persist a small file on the attachment disk and align path/size with the record.
     */
    public function withStoredDemoFile(): static
    {
        return $this->afterCreating(function (TaskAttachment $attachment): void {
            $disk = Storage::disk(TaskAttachment::STORAGE_DISK);
            $directory = sprintf(
                'task-attachments/%d/%d',
                $attachment->task->project_id,
                $attachment->task_id,
            );
            $extension = pathinfo($attachment->original_name, PATHINFO_EXTENSION) ?: 'bin';
            $path = $directory.'/'.Str::uuid()->toString().'.'.$extension;
            $contents = match ($attachment->mime_type) {
                'image/png' => base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', true) ?: 'png',
                'application/pdf' => '%PDF-1.4 demo attachment',
                default => "WorkBoard demo attachment\nGenerated for local seeding.\n",
            };

            $disk->put($path, $contents);

            $attachment->forceFill([
                'path' => $path,
                'size' => strlen($contents),
            ])->saveQuietly();
        });
    }
}
