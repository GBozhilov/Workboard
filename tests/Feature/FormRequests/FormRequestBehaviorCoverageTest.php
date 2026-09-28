<?php

namespace Tests\Feature\FormRequests;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesWorkBoardFixtures;
use Tests\TestCase;

class FormRequestBehaviorCoverageTest extends TestCase
{
    use CreatesWorkBoardFixtures;
    use RefreshDatabase;

    public function test_store_project_request_requires_authenticated_user(): void
    {
        $this->post(route('projects.store'), [
            'name' => 'Guest project',
            'description' => null,
        ])->assertRedirect(route('login'));
    }

    public function test_update_project_request_rejects_member(): void
    {
        ['member' => $member, 'project' => $project] = $this->sharedProject();

        $this->actingAs($member)
            ->put(route('projects.update', $project), [
                'name' => 'Denied update',
                'description' => null,
            ])
            ->assertForbidden();
    }

    public function test_store_task_request_rejects_invalid_priority_enum(): void
    {
        ['owner' => $owner, 'project' => $project] = $this->ownedProject();

        $this->actingAs($owner)
            ->from(route('projects.tasks.create', $project))
            ->post(route('projects.tasks.store', $project), [
                'title' => 'Bad priority',
                'description' => null,
                'status' => TaskStatus::Todo->value,
                'priority' => 'urgent',
                'due_date' => null,
            ])
            ->assertSessionHasErrors('priority');
    }

    public function test_store_task_request_rejects_malformed_due_date(): void
    {
        ['owner' => $owner, 'project' => $project] = $this->ownedProject();

        $this->actingAs($owner)
            ->from(route('projects.tasks.create', $project))
            ->post(route('projects.tasks.store', $project), [
                'title' => 'Bad date',
                'description' => null,
                'status' => TaskStatus::Todo->value,
                'priority' => TaskPriority::Medium->value,
                'due_date' => 'not-a-date',
            ])
            ->assertSessionHasErrors('due_date');
    }

    public function test_store_project_member_request_requires_manage_members_ability(): void
    {
        ['member' => $member, 'project' => $project] = $this->sharedProject();
        $candidate = User::factory()->create();

        $this->actingAs($member)
            ->post(route('projects.members.store', $project), ['email' => $candidate->email])
            ->assertForbidden();
    }

    public function test_index_project_request_requires_authentication(): void
    {
        $this->get(route('projects.index', ['search' => 'anything']))
            ->assertRedirect(route('login'));
    }
}
