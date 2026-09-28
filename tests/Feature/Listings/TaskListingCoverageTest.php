<?php

namespace Tests\Feature\Listings;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Http\Requests\FilterProjectTasksRequest;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesWorkBoardFixtures;
use Tests\TestCase;

class TaskListingCoverageTest extends TestCase
{
    use CreatesWorkBoardFixtures;
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string}>
     */
    public static function taskSortProvider(): array
    {
        $cases = [];
        foreach (FilterProjectTasksRequest::sortOptions() as $sort) {
            $cases[$sort] = [$sort];
        }

        return $cases;
    }

    #[DataProvider('taskSortProvider')]
    public function test_project_show_accepts_each_task_sort_option(string $sort): void
    {
        ['owner' => $owner, 'project' => $project] = $this->ownedProject();
        Task::factory()->for($project)->count(2)->create();

        $this->actingAs($owner)
            ->get(route('projects.show', ['project' => $project, 'sort' => $sort]))
            ->assertOk();
    }

    public function test_task_search_matches_description(): void
    {
        ['owner' => $owner, 'project' => $project] = $this->ownedProject();
        Task::factory()->for($project)->create([
            'title' => 'Unrelated title',
            'description' => 'needle-in-description',
        ]);
        Task::factory()->for($project)->create(['title' => 'Other task']);

        $this->actingAs($owner)
            ->get(route('projects.show', ['project' => $project, 'search' => 'needle']))
            ->assertOk()
            ->assertSee('Unrelated title', false)
            ->assertDontSee('Other task', false);
    }

    public function test_task_search_no_match_shows_filter_empty_state(): void
    {
        ['owner' => $owner, 'project' => $project] = $this->ownedProject();
        Task::factory()->for($project)->create(['title' => 'Visible task']);

        $this->actingAs($owner)
            ->get(route('projects.show', ['project' => $project, 'search' => 'nomatchxyz']))
            ->assertOk()
            ->assertSee('No tasks match your current search or filters', false);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function taskStatusFilterProvider(): array
    {
        return [
            'todo' => ['todo', 'Todo only'],
            'in_progress' => ['in_progress', 'In progress only'],
            'done' => ['done', 'Done only'],
        ];
    }

    #[DataProvider('taskStatusFilterProvider')]
    public function test_task_status_filter_isolates_tasks(string $status, string $title): void
    {
        ['owner' => $owner, 'project' => $project] = $this->ownedProject();
        Task::factory()->for($project)->create([
            'title' => $title,
            'status' => TaskStatus::from($status),
        ]);
        Task::factory()->for($project)->create([
            'title' => 'Other status task',
            'status' => $status === 'todo' ? TaskStatus::Done : TaskStatus::Todo,
        ]);

        $this->actingAs($owner)
            ->get(route('projects.show', ['project' => $project, 'status' => $status]))
            ->assertOk()
            ->assertSee($title, false)
            ->assertDontSee('Other status task', false);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function taskPriorityFilterProvider(): array
    {
        return [
            'low' => ['low', 'Low priority task'],
            'medium' => ['medium', 'Medium priority task'],
            'high' => ['high', 'High priority task'],
        ];
    }

    #[DataProvider('taskPriorityFilterProvider')]
    public function test_task_priority_filter_isolates_tasks(string $priority, string $title): void
    {
        ['owner' => $owner, 'project' => $project] = $this->ownedProject();
        Task::factory()->for($project)->create([
            'title' => $title,
            'priority' => TaskPriority::from($priority),
        ]);
        Task::factory()->for($project)->create([
            'title' => 'Different priority',
            'priority' => $priority === 'high' ? TaskPriority::Low : TaskPriority::High,
        ]);

        $this->actingAs($owner)
            ->get(route('projects.show', ['project' => $project, 'priority' => $priority]))
            ->assertOk()
            ->assertSee($title, false)
            ->assertDontSee('Different priority', false);
    }

    public function test_combined_search_status_and_priority_filters_tasks(): void
    {
        ['owner' => $owner, 'project' => $project] = $this->ownedProject();
        Task::factory()->for($project)->todo()->create([
            'title' => 'Match all filters',
            'priority' => TaskPriority::High,
        ]);
        Task::factory()->for($project)->completed()->create([
            'title' => 'Match all filters wrong status',
            'priority' => TaskPriority::High,
        ]);

        $this->actingAs($owner)
            ->get(route('projects.show', [
                'project' => $project,
                'search' => 'Match all',
                'status' => TaskStatus::Todo->value,
                'priority' => TaskPriority::High->value,
            ]))
            ->assertOk()
            ->assertSee('Match all filters', false)
            ->assertDontSee('Match all filters wrong status', false);
    }

    public function test_task_list_pagination_preserves_combined_filters(): void
    {
        ['owner' => $owner, 'project' => $project] = $this->ownedProject();

        foreach (range(1, 11) as $index) {
            Task::factory()->for($project)->todo()->create([
                'title' => "Filtered Task {$index}",
                'created_at' => now()->subMinutes(11 - $index),
            ]);
        }

        $response = $this->actingAs($owner)->get(route('projects.show', [
            'project' => $project,
            'status' => TaskStatus::Todo->value,
            'sort' => 'oldest',
            'page' => 2,
        ]));

        $response->assertOk()->assertSee('Filtered Task 11', false);
        $content = (string) $response->getContent();
        $this->assertStringContainsString('status=todo', $content);
        $this->assertStringContainsString('sort=oldest', $content);
    }

    public function test_task_due_asc_sort_puts_null_due_dates_last(): void
    {
        ['owner' => $owner, 'project' => $project] = $this->ownedProject();
        Task::factory()->for($project)->create([
            'title' => 'No due date',
            'due_date' => null,
        ]);
        Task::factory()->for($project)->create([
            'title' => 'Has due date',
            'due_date' => now()->addDay()->toDateString(),
        ]);

        $response = $this->actingAs($owner)->get(route('projects.show', [
            'project' => $project,
            'sort' => 'due_asc',
        ]));

        $content = (string) $response->getContent();
        $this->assertLessThan(strpos($content, 'No due date'), strpos($content, 'Has due date'));
    }
}
