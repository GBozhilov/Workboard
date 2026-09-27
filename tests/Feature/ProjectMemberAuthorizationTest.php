<?php

namespace Tests\Feature;

use App\Enums\ProjectRole;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectMemberAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function addMember(Project $project, User $member): void
    {
        $project->members()->attach($member->id, [
            'role' => ProjectRole::Member->value,
        ]);
    }

    public function test_owner_can_access_own_project(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner)->create(['name' => 'Owner Project']);

        $this->actingAs($owner)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('Owner Project');
    }

    public function test_member_can_access_project(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $project = Project::factory()->for($owner)->create(['name' => 'Shared Project']);
        $this->addMember($project, $member);

        $this->actingAs($member)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('Shared Project')
            ->assertSee('Members');
    }

    public function test_outsider_cannot_access_project(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $project = Project::factory()->for($owner)->create();

        $this->actingAs($outsider)
            ->get(route('projects.show', $project))
            ->assertForbidden();
    }

    public function test_member_sees_shared_project_on_index(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $project = Project::factory()->for($owner)->create(['name' => 'Member Visible']);
        $this->addMember($project, $member);

        $this->actingAs($member)
            ->get(route('projects.index'))
            ->assertOk()
            ->assertSee('Member Visible');
    }

    public function test_owner_can_add_member(): void
    {
        $owner = User::factory()->create();
        $newMember = User::factory()->create(['email' => 'member@workboard.test']);
        $project = Project::factory()->for($owner)->create();

        $this->actingAs($owner)
            ->post(route('projects.members.store', $project), [
                'email' => $newMember->email,
            ])
            ->assertRedirect(route('projects.show', $project));

        $this->assertDatabaseHas('project_user', [
            'project_id' => $project->id,
            'user_id' => $newMember->id,
            'role' => ProjectRole::Member->value,
        ]);
    }

    public function test_duplicate_member_is_rejected(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create(['email' => 'dup@workboard.test']);
        $project = Project::factory()->for($owner)->create();
        $this->addMember($project, $member);

        $this->actingAs($owner)
            ->from(route('projects.show', $project))
            ->post(route('projects.members.store', $project), [
                'email' => $member->email,
            ])
            ->assertRedirect(route('projects.show', $project))
            ->assertSessionHasErrors('email');
    }

    public function test_owner_can_remove_member(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $this->addMember($project, $member);

        $this->actingAs($owner)
            ->delete(route('projects.members.destroy', [$project, $member]))
            ->assertRedirect(route('projects.show', $project));

        $this->assertDatabaseMissing('project_user', [
            'project_id' => $project->id,
            'user_id' => $member->id,
        ]);
    }

    public function test_member_cannot_remove_another_member(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $otherMember = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $this->addMember($project, $member);
        $this->addMember($project, $otherMember);

        $this->actingAs($member)
            ->delete(route('projects.members.destroy', [$project, $otherMember]))
            ->assertForbidden();

        $this->assertDatabaseHas('project_user', [
            'project_id' => $project->id,
            'user_id' => $otherMember->id,
        ]);
    }

    public function test_owner_cannot_remove_themselves_from_project(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner)->create();

        $this->actingAs($owner)
            ->delete(route('projects.members.destroy', [$project, $owner]))
            ->assertRedirect(route('projects.show', $project))
            ->assertSessionHasErrors('member');

        $this->assertDatabaseHas('project_user', [
            'project_id' => $project->id,
            'user_id' => $owner->id,
            'role' => ProjectRole::Owner->value,
        ]);
    }

    public function test_member_cannot_delete_project(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $this->addMember($project, $member);

        $this->actingAs($member)
            ->delete(route('projects.destroy', $project))
            ->assertForbidden();

        $this->assertDatabaseHas('projects', ['id' => $project->id]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validTaskPayload(): array
    {
        return [
            'title' => 'Ship feature',
            'description' => 'Implement task board',
            'status' => TaskStatus::Todo->value,
            'priority' => TaskPriority::Medium->value,
            'due_date' => null,
        ];
    }

    public function test_member_can_create_and_update_tasks(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $this->addMember($project, $member);

        $valid = $this->validTaskPayload();

        $this->actingAs($member)
            ->post(route('projects.tasks.store', $project), $valid)
            ->assertRedirect();

        $task = Task::where('project_id', $project->id)->first();

        $this->actingAs($member)
            ->put(route('projects.tasks.update', [$project, $task]), array_merge($valid, [
                'title' => 'Member updated title',
            ]))
            ->assertRedirect(route('projects.tasks.show', [$project, $task]));

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'title' => 'Member updated title',
        ]);
    }

    public function test_member_cannot_delete_tasks(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $this->addMember($project, $member);
        $task = Task::factory()->for($project)->create();

        $this->actingAs($member)
            ->delete(route('projects.tasks.destroy', [$project, $task]))
            ->assertForbidden();

        $this->assertDatabaseHas('tasks', ['id' => $task->id]);
    }

    public function test_outsider_cannot_access_project_tasks(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $task = Task::factory()->for($project)->create();

        $this->actingAs($outsider)
            ->get(route('projects.tasks.show', [$project, $task]))
            ->assertForbidden();

        $this->actingAs($outsider)
            ->get(route('projects.tasks.create', $project))
            ->assertForbidden();
    }

    public function test_member_cannot_manage_members(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $candidate = User::factory()->create(['email' => 'new@workboard.test']);
        $project = Project::factory()->for($owner)->create();
        $this->addMember($project, $member);

        $this->actingAs($member)
            ->post(route('projects.members.store', $project), [
                'email' => $candidate->email,
            ])
            ->assertForbidden();
    }
}
