<?php

namespace Tests\Feature\Policies;

use App\Models\User;
use App\Policies\ProjectPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesWorkBoardFixtures;
use Tests\TestCase;

class ProjectPolicyCoverageTest extends TestCase
{
    use CreatesWorkBoardFixtures;
    use RefreshDatabase;

    private ProjectPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new ProjectPolicy;
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: bool}>
     */
    public static function projectPolicyMatrixProvider(): array
    {
        return [
            'viewAny owner' => ['viewAny', 'owner', true],
            'viewAny member' => ['viewAny', 'member', true],
            'viewAny outsider' => ['viewAny', 'outsider', true],
            'view owner' => ['view', 'owner', true],
            'view member' => ['view', 'member', true],
            'view outsider' => ['view', 'outsider', false],
            'create owner' => ['create', 'owner', true],
            'create member' => ['create', 'member', true],
            'create outsider' => ['create', 'outsider', true],
            'update owner' => ['update', 'owner', true],
            'update member' => ['update', 'member', false],
            'update outsider' => ['update', 'outsider', false],
            'delete owner' => ['delete', 'owner', true],
            'delete member' => ['delete', 'member', false],
            'delete outsider' => ['delete', 'outsider', false],
            'manageMembers owner' => ['manageMembers', 'owner', true],
            'manageMembers member' => ['manageMembers', 'member', false],
            'manageMembers outsider' => ['manageMembers', 'outsider', false],
        ];
    }

    #[DataProvider('projectPolicyMatrixProvider')]
    public function test_project_policy_matrix(string $ability, string $actorType, bool $expected): void
    {
        ['owner' => $owner, 'member' => $member, 'project' => $project] = $this->sharedProject();
        $outsider = User::factory()->create();

        $actor = match ($actorType) {
            'owner' => $owner,
            'member' => $member,
            default => $outsider,
        };

        $result = match ($ability) {
            'viewAny' => $this->policy->viewAny($actor),
            'view' => $this->policy->view($actor, $project),
            'create' => $this->policy->create($actor),
            'update' => $this->policy->update($actor, $project),
            'delete' => $this->policy->delete($actor, $project),
            'manageMembers' => $this->policy->manageMembers($actor, $project),
        };

        $this->assertSame($expected, $result);
    }
}
