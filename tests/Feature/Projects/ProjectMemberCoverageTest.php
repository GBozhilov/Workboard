<?php

namespace Tests\Feature\Projects;

use App\Enums\ProjectRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesWorkBoardFixtures;
use Tests\TestCase;

class ProjectMemberCoverageTest extends TestCase
{
    use CreatesWorkBoardFixtures;
    use RefreshDatabase;

    public function test_guest_cannot_add_project_member(): void
    {
        ['project' => $project] = $this->ownedProject();
        $candidate = User::factory()->create();

        $this->post(route('projects.members.store', $project), ['email' => $candidate->email])
            ->assertRedirect(route('login'));
    }

    public function test_outsider_cannot_add_project_member(): void
    {
        ['project' => $project] = $this->ownedProject();
        $outsider = User::factory()->create();
        $candidate = User::factory()->create();

        $this->actingAs($outsider)
            ->post(route('projects.members.store', $project), ['email' => $candidate->email])
            ->assertForbidden();
    }

    public function test_member_cannot_add_project_member(): void
    {
        ['member' => $member, 'project' => $project] = $this->sharedProject();
        $candidate = User::factory()->create();

        $this->actingAs($member)
            ->post(route('projects.members.store', $project), ['email' => $candidate->email])
            ->assertForbidden();
    }

    public function test_add_member_rejects_unknown_email(): void
    {
        ['owner' => $owner, 'project' => $project] = $this->ownedProject();

        $this->actingAs($owner)
            ->from(route('projects.show', $project))
            ->post(route('projects.members.store', $project), ['email' => 'missing@workboard.test'])
            ->assertSessionHasErrors('email');
    }

    public function test_add_member_rejects_invalid_email(): void
    {
        ['owner' => $owner, 'project' => $project] = $this->ownedProject();

        $this->actingAs($owner)
            ->from(route('projects.show', $project))
            ->post(route('projects.members.store', $project), ['email' => 'bad-email'])
            ->assertSessionHasErrors('email');
    }

    public function test_add_member_rejects_duplicate_membership(): void
    {
        ['owner' => $owner, 'member' => $member, 'project' => $project] = $this->sharedProject();

        $this->actingAs($owner)
            ->from(route('projects.show', $project))
            ->post(route('projects.members.store', $project), ['email' => $member->email])
            ->assertSessionHasErrors('email');
    }

    public function test_add_member_rejects_project_owner_email(): void
    {
        ['owner' => $owner, 'project' => $project] = $this->ownedProject();

        $this->actingAs($owner)
            ->from(route('projects.show', $project))
            ->post(route('projects.members.store', $project), ['email' => $owner->email])
            ->assertSessionHasErrors('email');
    }

    public function test_add_member_stores_member_role_on_pivot(): void
    {
        ['owner' => $owner, 'project' => $project] = $this->ownedProject();
        $candidate = User::factory()->create(['email' => 'newmember@workboard.test']);

        $this->actingAs($owner)
            ->post(route('projects.members.store', $project), ['email' => $candidate->email])
            ->assertRedirect();

        $this->assertSame(ProjectRole::Member->value, $project->members()->whereKey($candidate->id)->value('role'));
    }

    public function test_owner_can_remove_member(): void
    {
        ['owner' => $owner, 'member' => $member, 'project' => $project] = $this->sharedProject();

        $this->actingAs($owner)
            ->delete(route('projects.members.destroy', [$project, $member]))
            ->assertRedirect();

        $this->assertFalse($project->members()->whereKey($member->id)->exists());
    }

    public function test_owner_cannot_remove_self_as_member(): void
    {
        ['owner' => $owner, 'project' => $project] = $this->ownedProject();

        $this->actingAs($owner)
            ->delete(route('projects.members.destroy', [$project, $owner]))
            ->assertRedirect(route('projects.show', $project))
            ->assertSessionHasErrors('member');

        $this->assertTrue($project->members()->whereKey($owner->id)->exists());
    }

    public function test_member_cannot_remove_another_member(): void
    {
        ['member' => $member, 'project' => $project] = $this->sharedProject();
        $other = User::factory()->create();
        $this->attachProjectMember($project, $other);

        $this->actingAs($member)
            ->delete(route('projects.members.destroy', [$project, $other]))
            ->assertForbidden();
    }

    public function test_guest_cannot_remove_project_member(): void
    {
        ['owner' => $owner, 'project' => $project] = $this->sharedProject();

        $this->delete(route('projects.members.destroy', [$project, $owner]))
            ->assertRedirect(route('login'));
    }

    public function test_removing_non_member_returns_not_found(): void
    {
        ['owner' => $owner, 'project' => $project] = $this->ownedProject();
        $stranger = User::factory()->create();

        $this->actingAs($owner)
            ->delete(route('projects.members.destroy', [$project, $stranger]))
            ->assertNotFound();
    }

    public function test_user_member_projects_relationship_includes_shared_project(): void
    {
        ['member' => $member, 'project' => $project] = $this->sharedProject();

        $this->assertTrue($member->memberProjects()->whereKey($project->id)->exists());
    }
}
