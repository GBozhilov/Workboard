<?php

namespace Database\Factories;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->randomElement([
                'Platform Reliability',
                'Customer Experience',
                'Internal Tools',
                'Integration Hub',
                'Release Readiness',
            ]),
            'description' => fake()->optional(0.9)->paragraph(),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Project $project): void {
            $project->members()->syncWithoutDetaching([
                $project->user_id => ['role' => ProjectRole::Owner->value],
            ]);
        });
    }
}
