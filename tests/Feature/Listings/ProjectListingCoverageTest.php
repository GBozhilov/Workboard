<?php

namespace Tests\Feature\Listings;

use App\Http\Requests\IndexProjectRequest;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesWorkBoardFixtures;
use Tests\TestCase;

class ProjectListingCoverageTest extends TestCase
{
    use CreatesWorkBoardFixtures;
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string}>
     */
    public static function projectSortProvider(): array
    {
        $cases = [];
        foreach (IndexProjectRequest::sortOptions() as $sort) {
            $cases[$sort] = [$sort];
        }

        return $cases;
    }

    #[DataProvider('projectSortProvider')]
    public function test_projects_index_accepts_each_sort_option(string $sort): void
    {
        $user = User::factory()->create();
        Project::factory()->for($user)->count(2)->create();

        $this->actingAs($user)
            ->get(route('projects.index', ['sort' => $sort]))
            ->assertOk();
    }

    public function test_project_search_matches_partial_name_case_insensitively(): void
    {
        $user = User::factory()->create();
        Project::factory()->for($user)->create(['name' => 'CamelCase Project']);

        $this->actingAs($user)
            ->get(route('projects.index', ['search' => 'camelcase']))
            ->assertOk()
            ->assertSee('CamelCase Project', false);
    }

    public function test_project_search_with_no_match_shows_empty_state(): void
    {
        $user = User::factory()->create();
        Project::factory()->for($user)->create(['name' => 'Visible']);

        $this->actingAs($user)
            ->get(route('projects.index', ['search' => 'zzznomatch']))
            ->assertOk()
            ->assertSee('No matching projects', false);
    }

    public function test_inaccessible_project_matching_search_does_not_leak(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $secretProject = Project::factory()->for($owner)->create([
            'name' => 'Secret searchable',
            'description' => 'LEAK_MARKER_DESCRIPTION_XYZ',
        ]);

        $response = $this->actingAs($outsider)
            ->get(route('projects.index', ['search' => 'Secret searchable']));

        $response->assertOk();
        $response->assertDontSee(route('projects.show', $secretProject), false);
        $response->assertDontSee('LEAK_MARKER_DESCRIPTION_XYZ', false);
    }

    public function test_member_finds_shared_project_via_search(): void
    {
        ['member' => $member, 'project' => $project] = $this->sharedProject(['name' => 'Shared searchable name']);

        $this->actingAs($member)
            ->get(route('projects.index', ['search' => 'searchable']))
            ->assertOk()
            ->assertSee($project->name, false);
    }

    public function test_projects_index_second_page_returns_results(): void
    {
        $user = User::factory()->create();
        Project::factory()->for($user)->count(13)->create();

        $this->actingAs($user)
            ->get(route('projects.index', ['page' => 2]))
            ->assertOk();
    }

    public function test_projects_pagination_preserves_search_and_sort_query_string(): void
    {
        $user = User::factory()->create();
        Project::factory()->for($user)->count(13)->create(['name' => 'Paginated project']);

        $response = $this->actingAs($user)->get(route('projects.index', [
            'search' => 'Paginated',
            'sort' => 'name_asc',
            'page' => 2,
        ]));

        $response->assertOk();
        $content = (string) $response->getContent();
        $this->assertStringContainsString('search=Paginated', $content);
        $this->assertStringContainsString('sort=name_asc', $content);
    }

    public function test_project_sort_newest_orders_recent_first(): void
    {
        $user = User::factory()->create();
        Project::factory()->for($user)->create(['name' => 'Older', 'created_at' => now()->subDay()]);
        Project::factory()->for($user)->create(['name' => 'Newer', 'created_at' => now()]);

        $response = $this->actingAs($user)->get(route('projects.index', ['sort' => 'newest']));
        $content = (string) $response->getContent();
        $this->assertLessThan(strpos($content, 'Older'), strpos($content, 'Newer'));
    }

    public function test_project_sort_name_desc_orders_reverse_alphabetically(): void
    {
        $user = User::factory()->create();
        Project::factory()->for($user)->create(['name' => 'Alpha']);
        Project::factory()->for($user)->create(['name' => 'Zulu']);

        $response = $this->actingAs($user)->get(route('projects.index', ['sort' => 'name_desc']));
        $content = (string) $response->getContent();
        $this->assertLessThan(strpos($content, 'Alpha'), strpos($content, 'Zulu'));
    }
}
