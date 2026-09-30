<?php

namespace App\Support;

use App\Enums\TaskStatus;
use App\Models\Project;
use Illuminate\Support\Facades\Cache;

class ProjectSummaryCache
{
    private const TTL_SECONDS = 600;

    /**
     * @return array{
     *     total_tasks: int,
     *     tasks_todo: int,
     *     tasks_in_progress: int,
     *     tasks_done: int,
     *     member_count: int,
     * }
     */
    public function get(Project $project): array
    {
        return Cache::remember(
            self::cacheKey($project),
            self::TTL_SECONDS,
            fn (): array => $this->calculate($project),
        );
    }

    public static function forget(Project $project): void
    {
        Cache::forget(self::cacheKey($project));
    }

    public static function forgetForProjectId(int $projectId): void
    {
        Cache::forget(self::cacheKeyForId($projectId));
    }

    public static function cacheKey(Project $project): string
    {
        return self::cacheKeyForId($project->id);
    }

    public static function cacheKeyForId(int $projectId): string
    {
        return 'project:'.$projectId.':summary';
    }

    /**
     * @return array{
     *     total_tasks: int,
     *     tasks_todo: int,
     *     tasks_in_progress: int,
     *     tasks_done: int,
     *     member_count: int,
     * }
     */
    private function calculate(Project $project): array
    {
        $statusCounts = $project->tasks()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $todo = (int) ($statusCounts[TaskStatus::Todo->value] ?? 0);
        $inProgress = (int) ($statusCounts[TaskStatus::InProgress->value] ?? 0);
        $done = (int) ($statusCounts[TaskStatus::Done->value] ?? 0);

        return [
            'total_tasks' => $todo + $inProgress + $done,
            'tasks_todo' => $todo,
            'tasks_in_progress' => $inProgress,
            'tasks_done' => $done,
            'member_count' => $project->members()->count(),
        ];
    }
}
