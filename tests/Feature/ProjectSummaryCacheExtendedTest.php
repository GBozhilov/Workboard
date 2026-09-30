<?php

namespace Tests\Feature;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Support\ProjectSummaryCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesWorkBoardFixtures;
use Tests\TestCase;

class ProjectSummaryCacheExtendedTest extends TestCase
{
    use CreatesWorkBoardFixtures;
    use RefreshDatabase;

    private ProjectSummaryCache $summaryCache;

    protected function setUp(): void
    {
        parent::setUp();

        $this->summaryCache = new ProjectSummaryCache;
        Cache::flush();
    }

    public function test_empty_project_summary_counts_are_zero_except_members(): void
    {
        ['project' => $project] = $this->ownedProject();

        $this->assertSummary($project, [
            'total_tasks' => 0,
            'tasks_todo' => 0,
            'tasks_in_progress' => 0,
            'tasks_done' => 0,
            'member_count' => 1,
        ]);
    }

    public function test_single_task_project_summary_counts(): void
    {
        ['project' => $project] = $this->ownedProject();
        Task::factory()->for($project)->create(['status' => TaskStatus::InProgress]);

        $this->assertSummary($project, [
            'total_tasks' => 1,
            'tasks_todo' => 0,
            'tasks_in_progress' => 1,
            'tasks_done' => 0,
            'member_count' => 1,
        ]);
    }

    public function test_project_with_multiple_members_reports_exact_member_count(): void
    {
        ['project' => $project] = $this->ownedProject();
        $extraOne = User::factory()->create();
        $extraTwo = User::factory()->create();

        $this->attachProjectMember($project, $extraOne);
        $this->attachProjectMember($project, $extraTwo);

        $this->assertSummary($project, [
            'total_tasks' => 0,
            'tasks_todo' => 0,
            'tasks_in_progress' => 0,
            'tasks_done' => 0,
            'member_count' => 3,
        ]);
    }

    public function test_cache_hit_returns_stale_values_after_silent_database_change(): void
    {
        ['project' => $project] = $this->ownedProject();

        $this->assertSummary($project, ['total_tasks' => 0, 'tasks_todo' => 0]);

        Task::withoutEvents(function () use ($project): void {
            Task::factory()->for($project)->create(['status' => TaskStatus::Todo]);
            Task::factory()->for($project)->create(['status' => TaskStatus::Done]);
        });

        $this->assertSummary($project, [
            'total_tasks' => 0,
            'tasks_todo' => 0,
            'tasks_done' => 0,
        ]);
    }

    public function test_cache_miss_rebuilds_from_database_and_repopulates_cache(): void
    {
        ['project' => $project] = $this->ownedProject();
        Task::factory()->for($project)->create(['status' => TaskStatus::Todo]);
        $key = ProjectSummaryCache::cacheKey($project);

        $this->assertFalse(Cache::has($key));

        $this->assertSummary($project, ['total_tasks' => 1, 'tasks_todo' => 1]);
        $this->assertTrue(Cache::has($key));

        ProjectSummaryCache::forget($project);
        $this->assertFalse(Cache::has($key));

        $this->assertSummary($project, ['total_tasks' => 1, 'tasks_todo' => 1]);
        $this->assertTrue(Cache::has($key));
    }

    public function test_forget_causes_next_get_to_reflect_database_changes(): void
    {
        ['project' => $project] = $this->ownedProject();
        $this->summaryCache->get($project);

        Task::withoutEvents(fn () => Task::factory()->for($project)->create(['status' => TaskStatus::Todo]));

        $this->assertSummary($project, ['total_tasks' => 0]);

        ProjectSummaryCache::forget($project);

        $this->assertSummary($project, ['total_tasks' => 1, 'tasks_todo' => 1]);
    }

    public function test_isolated_projects_keep_independent_cached_summaries(): void
    {
        $projectA = Project::factory()->create();
        $projectB = Project::factory()->create();

        Task::factory()->for($projectA)->count(2)->create(['status' => TaskStatus::Todo]);
        Task::factory()->for($projectB)->count(5)->create(['status' => TaskStatus::Done]);

        $this->attachProjectMember($projectB, User::factory()->create());

        $this->assertSummary($projectA, [
            'total_tasks' => 2,
            'tasks_todo' => 2,
            'member_count' => 1,
        ]);
        $this->assertSummary($projectB, [
            'total_tasks' => 5,
            'tasks_done' => 5,
            'member_count' => 2,
        ]);

        $keyA = ProjectSummaryCache::cacheKey($projectA);
        $keyB = ProjectSummaryCache::cacheKey($projectB);

        Cache::put($keyA, [
            'total_tasks' => 111,
            'tasks_todo' => 111,
            'tasks_in_progress' => 0,
            'tasks_done' => 0,
            'member_count' => 9,
        ], 600);

        $this->assertSame(111, $this->summaryCache->get($projectA)['total_tasks']);
        $this->assertSame(5, $this->summaryCache->get($projectB)['total_tasks']);
    }

    public function test_invalidating_project_a_cache_does_not_remove_project_b_cache(): void
    {
        $projectA = Project::factory()->create();
        $projectB = Project::factory()->create();
        $keyA = ProjectSummaryCache::cacheKey($projectA);
        $keyB = ProjectSummaryCache::cacheKey($projectB);

        Cache::put($keyA, $this->dummySummary(1), 600);
        Cache::put($keyB, $this->dummySummary(2), 600);

        ProjectSummaryCache::forget($projectA);

        $this->assertFalse(Cache::has($keyA));
        $this->assertTrue(Cache::has($keyB));
        $this->assertSame(2, $this->summaryCache->get($projectB)['total_tasks']);
    }

    public function test_deleting_task_in_project_a_does_not_invalidate_project_b_cache(): void
    {
        ['owner' => $owner, 'project' => $projectA, 'task' => $taskA] = $this->ownedTask();
        $projectB = Project::factory()->for($owner)->create();
        $keyB = ProjectSummaryCache::cacheKey($projectB);

        Cache::put($keyB, $this->dummySummary(7), 600);

        $this->actingAs($owner)
            ->delete(route('projects.tasks.destroy', [$projectA, $taskA]))
            ->assertRedirect();

        $this->assertTrue(Cache::has($keyB));
        $this->assertSame(7, $this->summaryCache->get($projectB)['total_tasks']);
    }

    public function test_creating_task_with_in_progress_status_updates_cached_counts(): void
    {
        ['owner' => $owner, 'project' => $project] = $this->ownedProject();
        $this->summaryCache->get($project);

        $this->actingAs($owner)->post(route('projects.tasks.store', $project), [
            'title' => 'Active work',
            'description' => null,
            'status' => TaskStatus::InProgress->value,
            'priority' => 'medium',
            'due_date' => null,
        ])->assertRedirect();

        $this->assertSummary($project, [
            'total_tasks' => 1,
            'tasks_todo' => 0,
            'tasks_in_progress' => 1,
            'tasks_done' => 0,
        ]);
    }

    public function test_creating_done_task_increments_done_count_after_cache_rebuild(): void
    {
        ['owner' => $owner, 'project' => $project] = $this->ownedProject();
        Cache::put(ProjectSummaryCache::cacheKey($project), $this->dummySummary(0), 600);

        $this->actingAs($owner)->post(route('projects.tasks.store', $project), [
            'title' => 'Finished',
            'description' => null,
            'status' => TaskStatus::Done->value,
            'priority' => 'low',
            'due_date' => null,
        ])->assertRedirect();

        $this->assertSummary($project, [
            'total_tasks' => 1,
            'tasks_done' => 1,
        ]);
    }

    #[DataProvider('statusTransitionCountProvider')]
    public function test_status_transition_rebuilds_correct_status_counts(
        TaskStatus $from,
        TaskStatus $to,
        array $beforeCounts,
        array $afterCounts,
    ): void {
        ['owner' => $owner, 'project' => $project] = $this->ownedProject();

        foreach ($beforeCounts as $status => $count) {
            Task::factory()->for($project)->count($count)->create(['status' => TaskStatus::from($status)]);
        }

        $task = $project->tasks()->where('status', $from)->firstOrFail();
        $this->summaryCache->get($project);

        $this->actingAs($owner)->put(route('projects.tasks.update', [$project, $task]), [
            'title' => $task->title,
            'description' => $task->description,
            'status' => $to->value,
            'priority' => $task->priority->value,
            'due_date' => null,
        ])->assertRedirect();

        $this->assertSummary($project, $afterCounts);
    }

    /**
     * @return array<string, array{0: TaskStatus, 1: TaskStatus, 2: array<string, int>, 3: array<string, int>}>
     */
    public static function statusTransitionCountProvider(): array
    {
        return [
            'todo to in_progress' => [
                TaskStatus::Todo,
                TaskStatus::InProgress,
                ['todo' => 3, 'done' => 1],
                [
                    'total_tasks' => 4,
                    'tasks_todo' => 2,
                    'tasks_in_progress' => 1,
                    'tasks_done' => 1,
                    'member_count' => 1,
                ],
            ],
            'todo to done' => [
                TaskStatus::Todo,
                TaskStatus::Done,
                ['todo' => 3, 'done' => 1],
                [
                    'total_tasks' => 4,
                    'tasks_todo' => 2,
                    'tasks_in_progress' => 0,
                    'tasks_done' => 2,
                    'member_count' => 1,
                ],
            ],
            'in_progress to done' => [
                TaskStatus::InProgress,
                TaskStatus::Done,
                ['todo' => 1, 'in_progress' => 2, 'done' => 1],
                [
                    'total_tasks' => 4,
                    'tasks_todo' => 1,
                    'tasks_in_progress' => 1,
                    'tasks_done' => 2,
                    'member_count' => 1,
                ],
            ],
            'done to todo' => [
                TaskStatus::Done,
                TaskStatus::Todo,
                ['todo' => 1, 'done' => 2],
                [
                    'total_tasks' => 3,
                    'tasks_todo' => 2,
                    'tasks_in_progress' => 0,
                    'tasks_done' => 1,
                    'member_count' => 1,
                ],
            ],
        ];
    }

    public function test_updating_description_without_status_change_does_not_invalidate_cache(): void
    {
        ['owner' => $owner, 'project' => $project] = $this->ownedProject();
        $task = Task::factory()->for($project)->create(['status' => TaskStatus::Todo]);
        $key = ProjectSummaryCache::cacheKey($project);
        $cached = $this->dummySummary(1);
        Cache::put($key, $cached, 600);

        $this->actingAs($owner)->put(route('projects.tasks.update', [$project, $task]), [
            'title' => $task->title,
            'description' => 'Updated body only',
            'status' => TaskStatus::Todo->value,
            'priority' => $task->priority->value,
            'due_date' => null,
        ])->assertRedirect();

        $this->assertSame($cached, Cache::get($key));
    }

    public function test_updating_priority_without_status_change_does_not_invalidate_cache(): void
    {
        ['owner' => $owner, 'project' => $project] = $this->ownedProject();
        $task = Task::factory()->for($project)->create(['status' => TaskStatus::Todo]);
        $key = ProjectSummaryCache::cacheKey($project);
        $cached = $this->dummySummary(3, ['tasks_todo' => 3]);
        Cache::put($key, $cached, 600);

        $this->actingAs($owner)->put(route('projects.tasks.update', [$project, $task]), [
            'title' => $task->title,
            'description' => $task->description,
            'status' => TaskStatus::Todo->value,
            'priority' => TaskPriority::High->value,
            'due_date' => null,
        ])->assertRedirect();

        $this->assertTrue(Cache::has($key));
        $this->assertSame($cached, Cache::get($key));
    }

    public function test_updating_due_date_without_status_change_does_not_invalidate_cache(): void
    {
        ['owner' => $owner, 'project' => $project] = $this->ownedProject();
        $task = Task::factory()->for($project)->create(['status' => TaskStatus::Todo, 'due_date' => null]);
        $key = ProjectSummaryCache::cacheKey($project);
        $cached = $this->dummySummary(1);
        Cache::put($key, $cached, 600);

        $this->actingAs($owner)->put(route('projects.tasks.update', [$project, $task]), [
            'title' => $task->title,
            'description' => $task->description,
            'status' => TaskStatus::Todo->value,
            'priority' => $task->priority->value,
            'due_date' => '2030-01-15',
        ])->assertRedirect();

        $this->assertSame($cached, Cache::get($key));
    }

    public function test_adding_member_increases_member_count_exactly(): void
    {
        ['owner' => $owner, 'project' => $project] = $this->ownedProject();
        $newMember = User::factory()->create();
        $this->summaryCache->get($project);

        $this->actingAs($owner)->post(route('projects.members.store', $project), [
            'email' => $newMember->email,
            'role' => 'member',
        ])->assertRedirect();

        $this->assertSummary($project, ['member_count' => 2]);
    }

    public function test_removing_member_in_project_a_does_not_invalidate_project_b_cache(): void
    {
        ['owner' => $owner, 'member' => $member, 'project' => $projectA] = $this->sharedProject();
        $projectB = Project::factory()->for($owner)->create();
        $keyB = ProjectSummaryCache::cacheKey($projectB);
        Cache::put($keyB, $this->dummySummary(4, ['member_count' => 8]), 600);

        $this->actingAs($owner)
            ->delete(route('projects.members.destroy', [$projectA, $member]))
            ->assertRedirect();

        $this->assertTrue(Cache::has($keyB));
        $this->assertSame(8, $this->summaryCache->get($projectB)['member_count']);
    }

    public function test_duplicate_member_addition_does_not_invalidate_or_corrupt_cached_summary(): void
    {
        ['owner' => $owner, 'member' => $member, 'project' => $project] = $this->sharedProject();
        $key = ProjectSummaryCache::cacheKey($project);
        $cached = $this->dummySummary(2, ['member_count' => 5]);
        Cache::put($key, $cached, 600);

        $this->actingAs($owner)
            ->from(route('projects.show', $project))
            ->post(route('projects.members.store', $project), [
                'email' => $member->email,
                'role' => 'member',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('email');

        $this->assertTrue(Cache::has($key));
        $this->assertSame($cached, Cache::get($key));
        $this->assertSame(2, $project->members()->count());
    }

    public function test_unauthorized_member_addition_does_not_invalidate_cache(): void
    {
        ['member' => $member, 'project' => $project] = $this->sharedProject();
        $intruder = User::factory()->create();
        $key = ProjectSummaryCache::cacheKey($project);
        $cached = $this->dummySummary(1, ['member_count' => 2]);
        Cache::put($key, $cached, 600);

        $this->actingAs($intruder)
            ->post(route('projects.members.store', $project), [
                'email' => 'new@example.test',
                'role' => 'member',
            ])
            ->assertForbidden();

        $this->assertSame($cached, Cache::get($key));
    }

    public function test_invalid_task_creation_does_not_invalidate_cached_summary(): void
    {
        ['owner' => $owner, 'project' => $project] = $this->ownedProject();
        $key = ProjectSummaryCache::cacheKey($project);
        $cached = $this->dummySummary(4);
        Cache::put($key, $cached, 600);

        $this->actingAs($owner)
            ->from(route('projects.tasks.create', $project))
            ->post(route('projects.tasks.store', $project), ['title' => ''])
            ->assertSessionHasErrors('title');

        $this->assertSame($cached, Cache::get($key));
        $this->assertSame(0, $project->tasks()->count());
    }

    public function test_unauthorized_task_creation_does_not_invalidate_cache(): void
    {
        ['project' => $project] = $this->ownedProject();
        $outsider = User::factory()->create();
        $key = ProjectSummaryCache::cacheKey($project);
        $cached = $this->dummySummary(6);
        Cache::put($key, $cached, 600);

        $this->actingAs($outsider)
            ->post(route('projects.tasks.store', $project), [
                'title' => 'Blocked',
                'description' => null,
                'status' => 'todo',
                'priority' => 'medium',
                'due_date' => null,
            ])
            ->assertForbidden();

        $this->assertSame($cached, Cache::get($key));
        $this->assertSame(0, $project->tasks()->count());
    }

    public function test_unauthorized_task_deletion_does_not_invalidate_cache(): void
    {
        ['member' => $member, 'project' => $project] = $this->sharedProject();
        $task = Task::factory()->for($project)->create();
        $key = ProjectSummaryCache::cacheKey($project);
        $cached = $this->dummySummary(9);
        Cache::put($key, $cached, 600);

        $this->actingAs($member)
            ->delete(route('projects.tasks.destroy', [$project, $task]))
            ->assertForbidden();

        $this->assertSame($cached, Cache::get($key));
        $this->assertSame(1, $project->tasks()->count());
    }

    public function test_cache_key_is_stable_for_same_project_and_scoped_by_id(): void
    {
        $project = Project::factory()->create();

        $this->assertSame(
            ProjectSummaryCache::cacheKey($project),
            ProjectSummaryCache::cacheKey($project->fresh()),
        );
        $this->assertSame(
            'project:'.$project->id.':summary',
            ProjectSummaryCache::cacheKeyForId($project->id),
        );
    }

    public function test_summary_cache_expires_after_ttl_and_rebuilds_from_database(): void
    {
        ['project' => $project] = $this->ownedProject();
        $this->summaryCache->get($project);

        Task::withoutEvents(fn () => Task::factory()->for($project)->create(['status' => TaskStatus::Todo]));

        $this->assertSummary($project, ['total_tasks' => 0]);

        $this->travel(601)->seconds();

        $this->assertSummary($project, ['total_tasks' => 1, 'tasks_todo' => 1]);

        $this->travelBack();
    }

    public function test_sequential_task_mutations_yield_correct_final_summary(): void
    {
        ['owner' => $owner, 'project' => $project] = $this->ownedProject();
        $keep = Task::factory()->for($project)->create(['status' => TaskStatus::Todo]);
        $remove = Task::factory()->for($project)->create(['status' => TaskStatus::Todo]);

        $this->summaryCache->get($project);

        $this->actingAs($owner)->post(route('projects.tasks.store', $project), [
            'title' => 'Third',
            'description' => null,
            'status' => TaskStatus::Todo->value,
            'priority' => 'medium',
            'due_date' => null,
        ])->assertRedirect();

        $this->actingAs($owner)->put(route('projects.tasks.update', [$project, $keep]), [
            'title' => $keep->title,
            'description' => $keep->description,
            'status' => TaskStatus::Done->value,
            'priority' => $keep->priority->value,
            'due_date' => null,
        ])->assertRedirect();

        $this->actingAs($owner)
            ->delete(route('projects.tasks.destroy', [$project, $remove]))
            ->assertRedirect();

        $this->assertSummary($project, [
            'total_tasks' => 2,
            'tasks_todo' => 1,
            'tasks_done' => 1,
        ]);
    }

    public function test_outsider_cannot_view_project_even_when_summary_is_cached(): void
    {
        ['owner' => $owner, 'project' => $project] = $this->ownedProject();
        $outsider = User::factory()->create();

        $this->actingAs($owner)->get(route('projects.show', $project))->assertOk();
        $this->assertTrue(Cache::has(ProjectSummaryCache::cacheKey($project)));

        $this->actingAs($outsider)
            ->get(route('projects.show', $project))
            ->assertForbidden();
    }

    public function test_project_show_with_task_filters_still_renders_summary(): void
    {
        ['owner' => $owner, 'project' => $project] = $this->ownedProject();
        Task::factory()->for($project)->create(['title' => 'Filter me', 'status' => TaskStatus::Todo]);
        Task::factory()->for($project)->create(['title' => 'Other', 'status' => TaskStatus::Done]);

        $this->actingAs($owner)
            ->get(route('projects.show', ['project' => $project, 'status' => TaskStatus::Todo->value]))
            ->assertOk()
            ->assertSee('Project summary', false);

        $this->assertSummary($project, [
            'total_tasks' => 2,
            'tasks_todo' => 1,
            'tasks_done' => 1,
        ]);
    }

    /**
     * @param  array<string, int>  $expectedSubset
     */
    private function assertSummary(Project $project, array $expectedSubset): void
    {
        $summary = $this->summaryCache->get($project);

        foreach ($expectedSubset as $key => $value) {
            $this->assertArrayHasKey($key, $summary);
            $this->assertSame($value, $summary[$key], "Summary key [{$key}] mismatch.");
        }
    }

    /**
     * @param  array<string, int>  $overrides
     * @return array{
     *     total_tasks: int,
     *     tasks_todo: int,
     *     tasks_in_progress: int,
     *     tasks_done: int,
     *     member_count: int,
     * }
     */
    private function dummySummary(int $total, array $overrides = []): array
    {
        return array_merge([
            'total_tasks' => $total,
            'tasks_todo' => $total,
            'tasks_in_progress' => 0,
            'tasks_done' => 0,
            'member_count' => 1,
        ], $overrides);
    }
}
