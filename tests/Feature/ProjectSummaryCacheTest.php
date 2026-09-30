<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Support\ProjectSummaryCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\CreatesWorkBoardFixtures;
use Tests\TestCase;

class ProjectSummaryCacheTest extends TestCase
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

    public function test_project_summary_calculates_expected_counts(): void
    {
        ['project' => $project] = $this->sharedProject();

        Task::factory()->for($project)->create(['status' => TaskStatus::Todo]);
        Task::factory()->for($project)->create(['status' => TaskStatus::Todo]);
        Task::factory()->for($project)->create(['status' => TaskStatus::InProgress]);
        Task::factory()->for($project)->create(['status' => TaskStatus::Done]);

        $summary = $this->summaryCache->get($project);

        $this->assertSame(4, $summary['total_tasks']);
        $this->assertSame(2, $summary['tasks_todo']);
        $this->assertSame(1, $summary['tasks_in_progress']);
        $this->assertSame(1, $summary['tasks_done']);
        $this->assertSame(2, $summary['member_count']);
    }

    public function test_first_retrieval_stores_summary_in_cache(): void
    {
        ['project' => $project] = $this->ownedProject();
        $key = ProjectSummaryCache::cacheKey($project);

        $this->assertFalse(Cache::has($key));

        $this->summaryCache->get($project);

        $this->assertTrue(Cache::has($key));
    }

    public function test_subsequent_retrieval_uses_cached_value(): void
    {
        ['project' => $project] = $this->ownedProject();
        $key = ProjectSummaryCache::cacheKey($project);

        Cache::put($key, [
            'total_tasks' => 42,
            'tasks_todo' => 10,
            'tasks_in_progress' => 12,
            'tasks_done' => 20,
            'member_count' => 3,
        ], 600);

        $summary = $this->summaryCache->get($project);

        $this->assertSame(42, $summary['total_tasks']);
        $this->assertSame(3, $summary['member_count']);
    }

    public function test_projects_use_distinct_cache_keys(): void
    {
        $projectA = Project::factory()->create();
        $projectB = Project::factory()->create();

        $this->assertNotSame(
            ProjectSummaryCache::cacheKey($projectA),
            ProjectSummaryCache::cacheKey($projectB),
        );

        Cache::put(ProjectSummaryCache::cacheKey($projectA), [
            'total_tasks' => 1,
            'tasks_todo' => 1,
            'tasks_in_progress' => 0,
            'tasks_done' => 0,
            'member_count' => 1,
        ], 600);

        Cache::put(ProjectSummaryCache::cacheKey($projectB), [
            'total_tasks' => 9,
            'tasks_todo' => 9,
            'tasks_in_progress' => 0,
            'tasks_done' => 0,
            'member_count' => 2,
        ], 600);

        $this->assertSame(1, $this->summaryCache->get($projectA)['total_tasks']);
        $this->assertSame(9, $this->summaryCache->get($projectB)['total_tasks']);
    }

    public function test_creating_task_invalidates_project_summary_cache(): void
    {
        ['owner' => $owner, 'project' => $project] = $this->ownedProject();
        $key = ProjectSummaryCache::cacheKey($project);

        $this->summaryCache->get($project);
        $this->assertTrue(Cache::has($key));

        $this->actingAs($owner)->post(route('projects.tasks.store', $project), [
            'title' => 'New cached task',
            'description' => null,
            'status' => 'todo',
            'priority' => 'medium',
            'due_date' => null,
        ])->assertRedirect();

        $this->assertFalse(Cache::has($key));

        $summary = $this->summaryCache->get($project);
        $this->assertSame(1, $summary['total_tasks']);
    }

    public function test_deleting_task_invalidates_project_summary_cache(): void
    {
        ['owner' => $owner, 'project' => $project, 'task' => $task] = $this->ownedTask();
        $key = ProjectSummaryCache::cacheKey($project);

        $this->summaryCache->get($project);
        $this->assertTrue(Cache::has($key));

        $this->actingAs($owner)
            ->delete(route('projects.tasks.destroy', [$project, $task]))
            ->assertRedirect();

        $this->assertFalse(Cache::has($key));
        $this->assertSame(0, $this->summaryCache->get($project)['total_tasks']);
    }

    public function test_task_status_change_invalidates_cached_status_counts(): void
    {
        ['owner' => $owner, 'project' => $project] = $this->ownedProject();
        $task = Task::factory()->for($project)->create(['status' => TaskStatus::Todo]);
        $key = ProjectSummaryCache::cacheKey($project);

        $this->assertSame(1, $this->summaryCache->get($project)['tasks_todo']);

        Cache::put($key, [
            'total_tasks' => 1,
            'tasks_todo' => 1,
            'tasks_in_progress' => 0,
            'tasks_done' => 0,
            'member_count' => 1,
        ], 600);

        $this->actingAs($owner)->put(route('projects.tasks.update', [$project, $task]), [
            'title' => $task->title,
            'description' => $task->description,
            'status' => TaskStatus::Done->value,
            'priority' => $task->priority->value,
            'due_date' => null,
        ])->assertRedirect();

        $this->assertFalse(Cache::has($key));

        $summary = $this->summaryCache->get($project);
        $this->assertSame(0, $summary['tasks_todo']);
        $this->assertSame(1, $summary['tasks_done']);
    }

    public function test_adding_member_invalidates_member_count_cache(): void
    {
        ['owner' => $owner, 'project' => $project] = $this->ownedProject();
        $member = User::factory()->create();
        $key = ProjectSummaryCache::cacheKey($project);

        Cache::put($key, [
            'total_tasks' => 0,
            'tasks_todo' => 0,
            'tasks_in_progress' => 0,
            'tasks_done' => 0,
            'member_count' => 1,
        ], 600);

        $this->actingAs($owner)->post(route('projects.members.store', $project), [
            'email' => $member->email,
            'role' => 'member',
        ])->assertRedirect();

        $this->assertFalse(Cache::has($key));
        $this->assertGreaterThanOrEqual(2, $this->summaryCache->get($project)['member_count']);
    }

    public function test_removing_member_invalidates_member_count_cache(): void
    {
        ['owner' => $owner, 'member' => $member, 'project' => $project] = $this->sharedProject();
        $key = ProjectSummaryCache::cacheKey($project);

        Cache::put($key, [
            'total_tasks' => 0,
            'tasks_todo' => 0,
            'tasks_in_progress' => 0,
            'tasks_done' => 0,
            'member_count' => 99,
        ], 600);

        $this->actingAs($owner)
            ->delete(route('projects.members.destroy', [$project, $member]))
            ->assertRedirect();

        $this->assertFalse(Cache::has($key));
        $this->assertSame(1, $this->summaryCache->get($project)['member_count']);
    }

    public function test_comment_does_not_invalidate_project_summary_cache(): void
    {
        ['owner' => $owner, 'project' => $project, 'task' => $task] = $this->ownedTask();
        $key = ProjectSummaryCache::cacheKey($project);

        $cached = [
            'total_tasks' => 1,
            'tasks_todo' => 1,
            'tasks_in_progress' => 0,
            'tasks_done' => 0,
            'member_count' => 1,
        ];
        Cache::put($key, $cached, 600);

        $this->actingAs($owner)->post(route('projects.tasks.comments.store', [$project, $task]), [
            'body' => 'No cache bust',
        ])->assertRedirect();

        $this->assertTrue(Cache::has($key));
        $this->assertSame($cached, Cache::get($key));
    }

    public function test_project_show_displays_cached_summary_and_respects_authorization(): void
    {
        ['owner' => $owner, 'project' => $project] = $this->ownedProject();
        $outsider = User::factory()->create();

        $this->actingAs($owner)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('Project summary', false)
            ->assertSee('Total tasks', false);

        $this->actingAs($outsider)
            ->get(route('projects.show', $project))
            ->assertForbidden();
    }

    public function test_updating_task_title_without_status_change_does_not_invalidate_cache(): void
    {
        ['owner' => $owner, 'project' => $project] = $this->ownedProject();
        $task = Task::factory()->for($project)->create(['status' => TaskStatus::Todo]);
        $key = ProjectSummaryCache::cacheKey($project);

        $cached = [
            'total_tasks' => 5,
            'tasks_todo' => 5,
            'tasks_in_progress' => 0,
            'tasks_done' => 0,
            'member_count' => 1,
        ];
        Cache::put($key, $cached, 600);

        $this->actingAs($owner)->put(route('projects.tasks.update', [$project, $task]), [
            'title' => 'Renamed only',
            'description' => $task->description,
            'status' => TaskStatus::Todo->value,
            'priority' => $task->priority->value,
            'due_date' => null,
        ])->assertRedirect();

        $this->assertTrue(Cache::has($key));
        $this->assertSame($cached, Cache::get($key));
    }
}
