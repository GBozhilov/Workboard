<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Tag;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesWorkBoardFixtures;
use Tests\TestCase;

class TaskTagProjectScopeTest extends TestCase
{
    use CreatesWorkBoardFixtures;
    use RefreshDatabase;

    private function tagStoreUrl(Project $project, Task $task): string
    {
        return route('projects.tasks.tags.store', [$project, $task]);
    }

    private function tagDestroyUrl(Project $project, Task $task, Tag $tag): string
    {
        return route('projects.tasks.tags.destroy', [$project, $task, $tag]);
    }

    private function projectTag(Project $project, array $attributes = []): Tag
    {
        return Tag::factory()->for($project)->create($attributes);
    }

    public function test_project_has_many_tags(): void
    {
        $project = Project::factory()->create();
        $a = $this->projectTag($project, ['name' => 'alpha']);
        $b = $this->projectTag($project, ['name' => 'beta']);

        $project->refresh();

        $this->assertTrue($project->tags->contains($a));
        $this->assertTrue($project->tags->contains($b));
        $this->assertSame(['alpha', 'beta'], $project->tags->pluck('name')->all());
    }

    public function test_tag_belongs_to_project(): void
    {
        $project = Project::factory()->create();
        $tag = $this->projectTag($project);

        $this->assertTrue($tag->project->is($project));
    }

    public function test_detach_keeps_tag_on_other_tasks(): void
    {
        ['owner' => $owner, 'project' => $project, 'task' => $taskA] = $this->ownedTask();
        $taskB = Task::factory()->for($project)->create();
        $tag = $this->projectTag($project);
        $taskA->tags()->attach($tag->id);
        $taskB->tags()->attach($tag->id);

        $this->actingAs($owner)
            ->delete($this->tagDestroyUrl($project, $taskA, $tag))
            ->assertRedirect();

        $this->assertDatabaseHas('tags', ['id' => $tag->id]);
        $this->assertDatabaseMissing('tag_task', ['task_id' => $taskA->id, 'tag_id' => $tag->id]);
        $this->assertDatabaseHas('tag_task', ['task_id' => $taskB->id, 'tag_id' => $tag->id]);
    }

    public function test_cannot_attach_tag_from_another_project(): void
    {
        ['owner' => $owner, 'project' => $projectA, 'task' => $taskA] = $this->ownedTask();
        $projectB = Project::factory()->for($owner)->create();
        $foreignTag = $this->projectTag($projectB);

        $this->actingAs($owner)
            ->from(route('projects.tasks.show', [$projectA, $taskA]))
            ->post($this->tagStoreUrl($projectA, $taskA), ['tag_id' => $foreignTag->id])
            ->assertSessionHasErrors('tag_id');

        $this->assertDatabaseMissing('tag_task', [
            'task_id' => $taskA->id,
            'tag_id' => $foreignTag->id,
        ]);
    }

    public function test_cannot_detach_tag_through_wrong_project_url(): void
    {
        $owner = User::factory()->create();
        $projectA = Project::factory()->for($owner)->create();
        $projectB = Project::factory()->for($owner)->create();
        $taskB = Task::factory()->for($projectB)->create();
        $tag = $this->projectTag($projectB);
        $taskB->tags()->attach($tag->id);

        $this->actingAs($owner)
            ->delete($this->tagDestroyUrl($projectA, $taskB, $tag))
            ->assertNotFound();

        $this->assertDatabaseHas('tag_task', ['task_id' => $taskB->id, 'tag_id' => $tag->id]);
    }

    public function test_deleting_project_cascades_project_tags(): void
    {
        ['owner' => $owner, 'project' => $project, 'task' => $task] = $this->ownedTask();
        $tag = $this->projectTag($project);
        $task->tags()->attach($tag->id);
        $tagId = $tag->id;

        $this->actingAs($owner)->delete(route('projects.destroy', $project));

        $this->assertDatabaseMissing('tags', ['id' => $tagId]);
    }

    public function test_tag_filter_does_not_leak_tasks_from_another_project(): void
    {
        $owner = User::factory()->create();
        $projectA = Project::factory()->for($owner)->create();
        $projectB = Project::factory()->for($owner)->create();
        $taskA = Task::factory()->for($projectA)->create(['title' => 'Project A task']);
        $taskB = Task::factory()->for($projectB)->create(['title' => 'Project B task']);
        $tagA = $this->projectTag($projectA, ['name' => 'shared-name', 'slug' => 'shared-name']);
        $tagB = $this->projectTag($projectB, ['name' => 'shared-name', 'slug' => 'shared-name']);
        $taskA->tags()->attach($tagA->id);
        $taskB->tags()->attach($tagB->id);

        $this->actingAs($owner)
            ->get(route('projects.show', ['project' => $projectA, 'tag' => 'shared-name']))
            ->assertOk()
            ->assertSee('Project A task', false)
            ->assertDontSee('Project B task', false);
    }

    public function test_detach_makes_tag_available_in_attach_dropdown_ux_flow(): void
    {
        ['owner' => $owner, 'project' => $project, 'task' => $task] = $this->ownedTask();

        $backend = $this->projectTag($project, ['name' => 'backend', 'slug' => 'backend']);
        $frontend = $this->projectTag($project, ['name' => 'frontend', 'slug' => 'frontend']);
        $mysql = $this->projectTag($project, ['name' => 'mysql', 'slug' => 'mysql']);
        $testing = $this->projectTag($project, ['name' => 'testing', 'slug' => 'testing']);
        $urgent = $this->projectTag($project, ['name' => 'urgent', 'slug' => 'urgent']);

        $task->tags()->attach([$backend->id, $urgent->id]);

        $showUrl = route('projects.tasks.show', [$project, $task]);

        $this->actingAs($owner)
            ->get($showUrl)
            ->assertOk()
            ->assertSee('value="'.$frontend->id.'"', false)
            ->assertSee('value="'.$mysql->id.'"', false)
            ->assertSee('value="'.$testing->id.'"', false)
            ->assertDontSee('value="'.$backend->id.'"', false)
            ->assertDontSee('value="'.$urgent->id.'"', false);

        $this->actingAs($owner)
            ->delete($this->tagDestroyUrl($project, $task, $urgent))
            ->assertRedirect();

        $this->assertDatabaseHas('tags', ['id' => $urgent->id]);
        $this->assertDatabaseMissing('tag_task', ['task_id' => $task->id, 'tag_id' => $urgent->id]);

        $this->actingAs($owner)
            ->get($showUrl)
            ->assertOk()
            ->assertSee('value="'.$urgent->id.'"', false);

        $this->actingAs($owner)
            ->post($this->tagStoreUrl($project, $task), ['tag_id' => $mysql->id])
            ->assertRedirect();

        $this->assertDatabaseHas('tag_task', ['task_id' => $task->id, 'tag_id' => $mysql->id]);
        $this->assertSame(1, Tag::query()->where('project_id', $project->id)->where('slug', 'mysql')->count());

        $this->actingAs($owner)
            ->get($showUrl)
            ->assertOk()
            ->assertDontSee('value="'.$mysql->id.'"', false);
    }

    public function test_task_show_shows_empty_attach_state_when_no_available_tags(): void
    {
        ['owner' => $owner, 'project' => $project, 'task' => $task] = $this->ownedTask();
        $only = $this->projectTag($project, ['name' => 'solo']);
        $task->tags()->attach($only->id);

        $this->actingAs($owner)
            ->get(route('projects.tasks.show', [$project, $task]))
            ->assertOk()
            ->assertSee('No tags available to attach', false);
    }
}
