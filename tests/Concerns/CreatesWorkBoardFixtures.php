<?php

namespace Tests\Concerns;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;

trait CreatesWorkBoardFixtures
{
    protected function attachProjectMember(Project $project, User $member): void
    {
        $project->members()->attach($member->id, [
            'role' => ProjectRole::Member->value,
        ]);
    }

    /**
     * @return array{owner: User, project: Project}
     */
    protected function ownedProject(?User $owner = null, array $projectAttributes = []): array
    {
        $owner ??= User::factory()->create();
        $project = Project::factory()->for($owner)->create($projectAttributes);

        return compact('owner', 'project');
    }

    /**
     * @return array{owner: User, member: User, project: Project}
     */
    protected function sharedProject(array $projectAttributes = []): array
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $project = Project::factory()->for($owner)->create($projectAttributes);
        $this->attachProjectMember($project, $member);

        return compact('owner', 'member', 'project');
    }

    /**
     * @return array{owner: User, project: Project, task: Task}
     */
    protected function ownedTask(array $taskAttributes = []): array
    {
        ['owner' => $owner, 'project' => $project] = $this->ownedProject();
        $task = Task::factory()->for($project)->create($taskAttributes);

        return compact('owner', 'project', 'task');
    }
}
