<?php

namespace Tests\Feature;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectListingTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_search_matches_name_and_description(): void
    {
        $user = User::factory()->create();
        Project::factory()->for($user)->create(['name' => 'Visible Alpha', 'description' => 'misc']);
        Project::factory()->for($user)->create(['name' => 'Other', 'description' => 'find-in-description']);

        $this->actingAs($user)
            ->get(route('projects.index', ['search' => 'find-in-description']))
            ->assertOk()
            ->assertSee('Other')
            ->assertDontSee('Visible Alpha');

        $this->actingAs($user)
            ->get(route('projects.index', ['search' => 'Alpha']))
            ->assertOk()
            ->assertSee('Visible Alpha')
            ->assertDontSee('find-in-description');
    }

    public function test_project_sorting_by_name(): void
    {
        $user = User::factory()->create();
        Project::factory()->for($user)->create(['name' => 'Zulu Project']);
        Project::factory()->for($user)->create(['name' => 'Alpha Project']);

        $response = $this->actingAs($user)->get(route('projects.index', ['sort' => 'name_asc']));

        $response->assertOk();
        $this->assertTrue(
            strpos($response->getContent(), 'Alpha Project') < strpos($response->getContent(), 'Zulu Project')
        );
    }

    public function test_project_pagination_preserves_query_string(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 13) as $index) {
            Project::factory()->for($user)->create([
                'name' => "Paged Project {$index}",
                'created_at' => now()->subDays(13 - $index),
            ]);
        }

        $this->actingAs($user)
            ->get(route('projects.index', ['sort' => 'oldest']))
            ->assertOk()
            ->assertSee('Paged Project 1')
            ->assertDontSee('Paged Project 13');

        $this->actingAs($user)
            ->get(route('projects.index', ['sort' => 'oldest', 'page' => 2]))
            ->assertOk()
            ->assertSee('Paged Project 13');

        $this->actingAs($user)
            ->get(route('projects.index', ['sort' => 'oldest']))
            ->assertOk()
            ->assertSee('sort=oldest', false);
    }

    public function test_invalid_project_sort_falls_back_without_error(): void
    {
        $user = User::factory()->create();
        Project::factory()->for($user)->create(['name' => 'Still Listed']);

        $this->actingAs($user)
            ->get(route('projects.index', ['sort' => 'not-a-real-sort']))
            ->assertOk()
            ->assertSee('Still Listed');
    }

    public function test_member_only_sees_accessible_projects_in_index(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $outsider = User::factory()->create();

        $shared = Project::factory()->for($owner)->create(['name' => 'Shared With Member']);
        $shared->members()->attach($member->id, ['role' => ProjectRole::Member->value]);
        Project::factory()->for($owner)->create(['name' => 'Owner Only Secret']);

        $this->actingAs($member)
            ->get(route('projects.index'))
            ->assertOk()
            ->assertSee('Shared With Member')
            ->assertDontSee('Owner Only Secret');

        $this->actingAs($outsider)
            ->get(route('projects.index'))
            ->assertOk()
            ->assertDontSee('Shared With Member')
            ->assertDontSee('Owner Only Secret');
    }
}
