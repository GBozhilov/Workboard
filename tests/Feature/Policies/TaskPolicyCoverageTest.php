<?php

namespace Tests\Feature\Policies;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Policies\TaskPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesWorkBoardFixtures;
use Tests\TestCase;

class TaskPolicyCoverageTest extends TestCase
{
    use CreatesWorkBoardFixtures;
    use RefreshDatabase;

    private TaskPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new TaskPolicy;
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: bool}>
     */
    public static function taskAccessPolicyProvider(): array
    {
        return [
            'viewAny owner' => ['viewAny', 'owner', true],
            'viewAny member' => ['viewAny', 'member', true],
            'viewAny outsider' => ['viewAny', 'outsider', false],
            'view owner' => ['view', 'owner', true],
            'view member' => ['view', 'member', true],
            'view outsider' => ['view', 'outsider', false],
            'create owner' => ['create', 'owner', true],
            'create member' => ['create', 'member', true],
            'create outsider' => ['create', 'outsider', false],
            'update owner' => ['update', 'owner', true],
            'update member' => ['update', 'member', true],
            'update outsider' => ['update', 'outsider', false],
        ];
    }

    #[DataProvider('taskAccessPolicyProvider')]
    public function test_task_access_policy_matrix(string $ability, string $actorType, bool $expected): void
    {
        ['owner' => $owner, 'member' => $member, 'project' => $project] = $this->sharedProject();
        $task = Task::factory()->for($project)->create();
        $outsider = User::factory()->create();

        $actor = match ($actorType) {
            'owner' => $owner,
            'member' => $member,
            default => $outsider,
        };

        $result = match ($ability) {
            'viewAny' => $this->policy->viewAny($actor, $project),
            'view' => $this->policy->view($actor, $task),
            'create' => $this->policy->create($actor, $project),
            'update' => $this->policy->update($actor, $task),
        };

        $this->assertSame($expected, $result);
    }

    public function test_task_delete_allowed_for_project_owner(): void
    {
        ['owner' => $owner, 'project' => $project] = $this->ownedProject();
        $task = Task::factory()->for($project)->create();

        $this->assertTrue($this->policy->delete($owner, $task));
    }

    public function test_task_delete_denied_for_project_member(): void
    {
        ['member' => $member, 'project' => $project] = $this->sharedProject();
        $task = Task::factory()->for($project)->create();

        $this->assertFalse($this->policy->delete($member, $task));
    }

    public function test_task_delete_denied_for_outsider(): void
    {
        ['project' => $project] = $this->ownedProject();
        $task = Task::factory()->for($project)->create();
        $outsider = User::factory()->create();

        $this->assertFalse($this->policy->delete($outsider, $task));
    }

    public function test_task_view_denied_for_outsider_on_foreign_project_task(): void
    {
        ['project' => $project] = $this->ownedProject();
        $task = Task::factory()->for($project)->create();
        $outsider = User::factory()->create();

        $this->assertFalse($this->policy->view($outsider, $task));
    }
}
