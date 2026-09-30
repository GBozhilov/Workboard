<?php

namespace Tests\Feature\Api;

use App\Enums\TaskStatus;
use App\Events\TaskCreated;
use App\Models\Project;
use App\Models\Tag;
use App\Models\Task;
use App\Models\User;
use App\Support\ProjectSummaryCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\CreatesWorkBoardFixtures;
use Tests\Concerns\InteractsWithSanctum;
use Tests\TestCase;

class ApiTaskTest extends TestCase
{
    use CreatesWorkBoardFixtures;
    use InteractsWithSanctum;
    use RefreshDatabase;

    public function test_lists_tasks_for_project(): void
    {
        ['member' => $member, 'project' => $project] = $this->sharedProject();
        Task::factory()->for($project)->count(2)->create();

        $this->actingAsSanctum($member)
            ->getJson("/api/projects/{$project->id}/tasks")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_creates_task_and_dispatches_task_created_event(): void
    {
        Event::fake([TaskCreated::class]);
        ['member' => $member, 'project' => $project] = $this->sharedProject();

        $this->actingAsSanctum($member)
            ->postJson("/api/projects/{$project->id}/tasks", [
                'title' => 'API task',
                'description' => null,
                'status' => 'todo',
                'priority' => 'medium',
                'due_date' => null,
            ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'API task');

        Event::assertDispatched(TaskCreated::class);
    }

    public function test_task_create_validates_enums(): void
    {
        ['owner' => $owner, 'project' => $project] = $this->ownedProject();

        $this->actingAsSanctum($owner)
            ->postJson("/api/projects/{$project->id}/tasks", [
                'title' => 'Bad enums',
                'status' => 'not-a-status',
                'priority' => 'not-a-priority',
            ])
            ->assertUnprocessable();
    }

    public function test_show_task_returns_expected_fields_without_sensitive_data(): void
    {
        ['owner' => $owner, 'project' => $project] = $this->ownedProject();
        $task = Task::factory()->for($project)->create();

        $this->actingAsSanctum($owner)
            ->getJson("/api/projects/{$project->id}/tasks/{$task->id}")
            ->assertOk()
            ->assertJsonStructure([
                'data' => ['id', 'title', 'status', 'priority', 'comments_count', 'attachments_count'],
            ])
            ->assertJsonMissing(['password', 'remember_token', 'storage_path']);
    }

    public function test_task_from_another_project_returns_404_on_nested_route(): void
    {
        ['owner' => $owner, 'project' => $projectA] = $this->ownedProject();
        $projectB = Project::factory()->for($owner)->create();
        $taskOnB = Task::factory()->for($projectB)->create();

        $this->actingAsSanctum($owner)
            ->getJson("/api/projects/{$projectA->id}/tasks/{$taskOnB->id}")
            ->assertNotFound();
    }

    public function test_update_and_delete_mismatch_return_404(): void
    {
        ['owner' => $owner, 'project' => $projectA] = $this->ownedProject();
        $projectB = Project::factory()->for($owner)->create();
        $taskOnB = Task::factory()->for($projectB)->create();

        $this->actingAsSanctum($owner)
            ->patchJson("/api/projects/{$projectA->id}/tasks/{$taskOnB->id}", [
                'title' => 'Nope',
                'status' => TaskStatus::Todo->value,
                'priority' => 'medium',
            ])
            ->assertNotFound();

        $this->actingAsSanctum($owner)
            ->deleteJson("/api/projects/{$projectA->id}/tasks/{$taskOnB->id}")
            ->assertNotFound();
    }

    public function test_member_cannot_delete_task(): void
    {
        ['member' => $member, 'project' => $project] = $this->sharedProject();
        $task = Task::factory()->for($project)->create();

        $this->actingAsSanctum($member)
            ->deleteJson("/api/projects/{$project->id}/tasks/{$task->id}")
            ->assertForbidden();
    }

    public function test_task_filters_search_status_and_sort(): void
    {
        ['owner' => $owner, 'project' => $project] = $this->ownedProject();
        Task::factory()->for($project)->create(['title' => 'Alpha', 'status' => TaskStatus::Todo]);
        Task::factory()->for($project)->create(['title' => 'Beta', 'status' => TaskStatus::Done]);

        $this->actingAsSanctum($owner)
            ->getJson("/api/projects/{$project->id}/tasks?search=Alpha&status=todo&sort=title_asc")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Alpha');
    }

    public function test_api_task_creation_invalidates_project_summary_cache(): void
    {
        Cache::flush();
        ['member' => $member, 'project' => $project] = $this->sharedProject();
        $key = ProjectSummaryCache::cacheKey($project);
        app(ProjectSummaryCache::class)->get($project);
        $this->assertTrue(Cache::has($key));

        $this->actingAsSanctum($member)
            ->postJson("/api/projects/{$project->id}/tasks", [
                'title' => 'Cache bust',
                'description' => null,
                'status' => 'todo',
                'priority' => 'medium',
                'due_date' => null,
            ])
            ->assertCreated();

        $this->assertFalse(Cache::has($key));
    }

    public function test_outsider_cannot_create_tasks(): void
    {
        ['project' => $project] = $this->ownedProject();
        $outsider = User::factory()->create();

        $this->actingAsSanctum($outsider)
            ->postJson("/api/projects/{$project->id}/tasks", [
                'title' => 'Blocked',
                'status' => 'todo',
                'priority' => 'medium',
            ])
            ->assertForbidden();
    }
}
