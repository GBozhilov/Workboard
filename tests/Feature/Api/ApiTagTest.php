<?php

namespace Tests\Feature\Api;

use App\Models\Project;
use App\Models\Tag;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesWorkBoardFixtures;
use Tests\Concerns\InteractsWithSanctum;
use Tests\TestCase;

class ApiTagTest extends TestCase
{
    use CreatesWorkBoardFixtures;
    use InteractsWithSanctum;
    use RefreshDatabase;

    public function test_lists_project_tags(): void
    {
        ['owner' => $owner, 'project' => $project] = $this->ownedProject();
        Tag::factory()->for($project)->create(['name' => 'api-tag']);

        $this->actingAsSanctum($owner)
            ->getJson("/api/projects/{$project->id}/tags")
            ->assertOk()
            ->assertJsonPath('data.0.name', 'api-tag');
    }

    public function test_creates_and_attaches_tag_by_name(): void
    {
        ['owner' => $owner, 'project' => $project, 'task' => $task] = $this->ownedTask();

        $this->actingAsSanctum($owner)
            ->postJson("/api/projects/{$project->id}/tasks/{$task->id}/tags", [
                'name' => 'New Api Tag',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('tags', [
            'project_id' => $project->id,
            'name' => 'New Api Tag',
        ]);
        $this->assertSame(1, $task->tags()->count());
    }

    public function test_attach_existing_project_tag_endpoint(): void
    {
        ['owner' => $owner, 'project' => $project, 'task' => $task] = $this->ownedTask();
        $tag = Tag::factory()->for($project)->create();

        $this->actingAsSanctum($owner)
            ->postJson("/api/projects/{$project->id}/tasks/{$task->id}/tags/{$tag->id}/attach")
            ->assertOk();

        $this->assertTrue($task->tags()->whereKey($tag->id)->exists());
    }

    public function test_duplicate_attach_does_not_duplicate_pivot(): void
    {
        ['owner' => $owner, 'project' => $project, 'task' => $task] = $this->ownedTask();
        $tag = Tag::factory()->for($project)->create();
        $task->tags()->attach($tag->id);

        $this->actingAsSanctum($owner)
            ->postJson("/api/projects/{$project->id}/tasks/{$task->id}/tags/{$tag->id}/attach")
            ->assertOk();

        $this->assertSame(1, $task->tags()->count());
    }

    public function test_detach_removes_pivot_but_keeps_project_tag(): void
    {
        ['owner' => $owner, 'project' => $project, 'task' => $task] = $this->ownedTask();
        $tag = Tag::factory()->for($project)->create();
        $task->tags()->attach($tag->id);

        $this->actingAsSanctum($owner)
            ->deleteJson("/api/projects/{$project->id}/tasks/{$task->id}/tags/{$tag->id}")
            ->assertNoContent();

        $this->assertSame(0, $task->tags()->count());
        $this->assertDatabaseHas('tags', ['id' => $tag->id]);
    }

    public function test_cannot_attach_tag_from_another_project(): void
    {
        ['owner' => $owner, 'project' => $project, 'task' => $task] = $this->ownedTask();
        $otherProject = Project::factory()->for($owner)->create();
        $foreignTag = Tag::factory()->for($otherProject)->create();

        $this->actingAsSanctum($owner)
            ->postJson("/api/projects/{$project->id}/tasks/{$task->id}/tags/{$foreignTag->id}/attach")
            ->assertNotFound();
    }

    public function test_outsider_cannot_list_project_tags(): void
    {
        ['project' => $project] = $this->ownedProject();
        $outsider = User::factory()->create();

        $this->actingAsSanctum($outsider)
            ->getJson("/api/projects/{$project->id}/tags")
            ->assertForbidden();
    }
}
