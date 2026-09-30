<?php

namespace Tests\Unit;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use App\Support\WorkspaceOverviewQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesWorkBoardFixtures;
use Tests\TestCase;

class WorkspaceOverviewQueryTest extends TestCase
{
    use CreatesWorkBoardFixtures;
    use RefreshDatabase;

    public function test_counts_only_accessible_open_tasks_and_assignments(): void
    {
        ['owner' => $owner, 'member' => $member, 'project' => $sharedProject] = $this->sharedProject();
        Task::factory()->for($sharedProject)->todo()->create();
        Task::factory()->for($sharedProject)->inProgress()->create();
        Task::factory()->for($sharedProject)->completed()->create();
        Task::factory()->for($sharedProject)->todo()->assignedTo($member)->create();

        $stranger = User::factory()->create();
        ['project' => $privateProject] = $this->ownedProject($stranger);
        Task::factory()->for($privateProject)->todo()->assignedTo($member)->create();

        $overview = (new WorkspaceOverviewQuery)->forUser($member);

        $this->assertSame(1, $overview->accessibleProjectsCount);
        $this->assertSame(3, $overview->openTasksCount);
        $this->assertSame(1, $overview->assignedToMeOpenTasksCount);
    }

    public function test_completed_tasks_are_excluded_from_open_counts(): void
    {
        $user = User::factory()->create();
        ['project' => $project] = $this->ownedProject($user);
        Task::factory()->for($project)->completed()->assignedTo($user)->create();

        $overview = (new WorkspaceOverviewQuery)->forUser($user);

        $this->assertSame(0, $overview->openTasksCount);
        $this->assertSame(0, $overview->assignedToMeOpenTasksCount);
    }
}
