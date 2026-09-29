<?php

namespace Tests\Unit\Models;

use App\Models\Project;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TagModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_unique_slug_appends_suffix_on_collision_within_project(): void
    {
        $project = Project::factory()->create();
        Tag::factory()->for($project)->create(['slug' => 'collision']);

        $slug = Tag::generateUniqueSlugForProject($project, 'collision');

        $this->assertSame('collision-1', $slug);
    }

    public function test_find_or_create_for_project_trims_whitespace(): void
    {
        $project = Project::factory()->create();

        $tag = Tag::findOrCreateForProject($project, '  trimmed  ');

        $this->assertSame('trimmed', $tag->name);
        $this->assertSame($project->id, $tag->project_id);
        $this->assertSame(Str::slug('trimmed'), $tag->slug);
    }

    public function test_same_tag_name_can_exist_on_different_projects(): void
    {
        $projectA = Project::factory()->create();
        $projectB = Project::factory()->create();

        $tagA = Tag::findOrCreateForProject($projectA, 'backend');
        $tagB = Tag::findOrCreateForProject($projectB, 'backend');

        $this->assertNotSame($tagA->id, $tagB->id);
        $this->assertSame('backend', $tagA->name);
        $this->assertSame('backend', $tagB->name);
    }
}
