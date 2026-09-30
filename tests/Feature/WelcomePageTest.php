<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskCreatedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesWorkBoardFixtures;
use Tests\TestCase;

class WelcomePageTest extends TestCase
{
    use CreatesWorkBoardFixtures;
    use RefreshDatabase;

    public function test_guest_sees_login_and_register_without_workspace_stats(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('Log in', false);
        $response->assertSee(route('register'), false);
        $response->assertDontSee('Your workspace', false);
        $response->assertDontSee('Coming soon', false);
        $response->assertDontSee('foundation release', false);
        $response->assertDontSee('>Tasks</', false);
        $response->assertDontSee('>Team</', false);
    }

    public function test_authenticated_user_sees_cleaned_navigation(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('home'));

        $response->assertOk();
        $response->assertSee('Home', false);
        $response->assertSee('Dashboard', false);
        $response->assertSee('Projects', false);
        $response->assertSee('Notifications', false);
        $response->assertSee($user->name, false);
        $response->assertDontSee('Coming in a later stage', false);
        $response->assertDontSee('>Tasks</', false);
        $response->assertDontSee('>Team</', false);
    }

    public function test_authenticated_user_sees_hero_actions_and_create_project_link(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('home'));

        $response->assertSee('View Projects', false);
        $response->assertSee(route('projects.index'), false);
        $response->assertSee('Create Project', false);
        $response->assertSee(route('projects.create'), false);
        $response->assertDontSee('Create Task', false);
    }

    public function test_home_overview_counts_reflect_accessible_data_only(): void
    {
        ['owner' => $owner, 'member' => $member, 'project' => $sharedProject] = $this->sharedProject();
        Task::factory()->for($sharedProject)->todo()->create();
        Task::factory()->for($sharedProject)->inProgress()->create();
        Task::factory()->for($sharedProject)->completed()->create();

        $assignedTask = Task::factory()->for($sharedProject)->todo()->assignedTo($member)->create();

        $stranger = User::factory()->create();
        ['project' => $privateProject] = $this->ownedProject($stranger);
        Task::factory()->for($privateProject)->todo()->create();

        $member->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => TaskCreatedNotification::class,
            'data' => ['type' => 'task_created', 'message' => 'Test'],
        ]);

        $response = $this->actingAs($member)->get(route('home'));

        $response->assertOk();
        $response->assertSee('Your workspace', false);
        $response->assertSee('data-testid="overview-projects-count">1<', false);
        $response->assertSee('data-testid="overview-open-tasks-count">3<', false);
        $response->assertSee('data-testid="overview-unread-notifications-count">1<', false);
        $response->assertSee('data-testid="overview-assigned-count">1<', false);

        $this->assertSame($assignedTask->id, Task::query()
            ->where('project_id', $sharedProject->id)
            ->where('assigned_to', $member->id)
            ->whereIn('status', [TaskStatus::Todo, TaskStatus::InProgress])
            ->value('id'));
    }

    public function test_owner_overview_includes_owned_and_open_tasks(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        Task::factory()->for($project)->todo()->count(2)->create();
        Task::factory()->for($project)->completed()->create();

        $response = $this->actingAs($user)->get(route('home'));

        $response->assertOk();
        $response->assertSee('data-testid="overview-projects-count">1<', false);
        $response->assertSee('data-testid="overview-open-tasks-count">2<', false);
    }
}
