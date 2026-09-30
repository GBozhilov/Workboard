<?php

namespace Database\Factories;

use App\Models\Comment;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Comment>
 */
class CommentFactory extends Factory
{
    protected $model = Comment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'task_id' => Task::factory(),
            'user_id' => User::factory(),
            'body' => fake()->sentences(random_int(1, 2), true),
        ];
    }

    public function realistic(): static
    {
        $snippets = [
            'I can take this after the API changes land.',
            'Can we confirm the expected behavior for members vs owners?',
            'Added notes from today’s review — please check the description.',
            'This looks good to me; ready for a quick pass in staging.',
            'Blocking on the related webhook verification work.',
            'I reproduced the issue and left steps in the task body.',
            'Suggest we split this into two smaller tasks.',
            'Updated the due date after talking with the team.',
        ];

        return $this->state(fn () => [
            'body' => fake()->randomElement($snippets),
        ]);
    }
}
