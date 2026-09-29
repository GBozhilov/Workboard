<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Http\Requests\StoreTaskTagRequest;
use App\Models\Project;
use App\Models\Tag;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesWorkBoardFixtures;
use Tests\TestCase;

class TaskTagTest extends TestCase
{
    use CreatesWorkBoardFixtures;
    use RefreshDatabase;

    private function tagStoreUrl($project, $task): string
    {
        return route('projects.tasks.tags.store', [$project, $task]);
    }

    private function tagDestroyUrl($project, $task, $tag): string
    {
        return route('projects.tasks.tags.destroy', [$project, $task, $tag]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function projectTag(Project $project, array $attributes = []): Tag
    {
        return Tag::factory()->for($project)->create($attributes);
    }

    public function test_task_belongs_to_many_tags(): void
    {
        ['project' => $project, 'task' => $task] = $this->ownedTask();
        $tag = $this->projectTag($project);
        $task->tags()->attach($tag->id);

        $this->assertTrue($task->tags->contains($tag));
    }

    public function test_tag_belongs_to_many_tasks(): void
    {
        ['project' => $project] = $this->ownedProject();
        $taskA = Task::factory()->for($project)->create();
        $taskB = Task::factory()->for($project)->create();
        $tag = $this->projectTag($project);
        $tag->tasks()->attach([$taskA->id, $taskB->id]);

        $this->assertCount(2, $tag->tasks);
    }

    public function test_duplicate_task_tag_pivot_is_prevented(): void
    {
        ['project' => $project, 'task' => $task] = $this->ownedTask();
        $tag = $this->projectTag($project);

        $task->tags()->attach($tag->id);

        $this->expectException(QueryException::class);

        DB::table('tag_task')->insert([
            'tag_id' => $tag->id,
            'task_id' => $task->id,
        ]);
    }

    public function test_deleting_task_cascades_pivot_rows(): void
    {
        ['owner' => $owner, 'project' => $project, 'task' => $task] = $this->ownedTask();
        $tag = $this->projectTag($project);
        $task->tags()->attach($tag->id);

        $this->actingAs($owner)->delete(route('projects.tasks.destroy', [$project, $task]));

        $this->assertDatabaseMissing('tag_task', [
            'task_id' => $task->id,
            'tag_id' => $tag->id,
        ]);
        $this->assertDatabaseHas('tags', ['id' => $tag->id]);
    }

    public function test_deleting_tag_cascades_pivot_rows(): void
    {
        ['project' => $project, 'task' => $task] = $this->ownedTask();
        $tag = $this->projectTag($project);
        $task->tags()->attach($tag->id);

        $tag->delete();

        $this->assertDatabaseMissing('tag_task', [
            'task_id' => $task->id,
            'tag_id' => $tag->id,
        ]);
    }

    public function test_owner_can_create_and_attach_tag_by_name(): void
    {
        ['owner' => $owner, 'project' => $project, 'task' => $task] = $this->ownedTask();

        $this->actingAs($owner)
            ->post($this->tagStoreUrl($project, $task), ['name' => 'backend'])
            ->assertRedirect(route('projects.tasks.show', [$project, $task]));

        $this->assertDatabaseHas('tags', ['name' => 'backend', 'project_id' => $project->id]);
        $this->assertDatabaseHas('tag_task', [
            'task_id' => $task->id,
        ]);
    }

    public function test_member_can_create_and_attach_tag(): void
    {
        ['member' => $member, 'project' => $project] = $this->sharedProject();
        $task = Task::factory()->for($project)->create();

        $this->actingAs($member)
            ->post($this->tagStoreUrl($project, $task), ['name' => 'shared-tag'])
            ->assertRedirect();

        $this->assertDatabaseHas('tag_task', ['task_id' => $task->id]);
    }

    public function test_outsider_cannot_attach_tag(): void
    {
        ['project' => $project, 'task' => $task] = $this->ownedTask();
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->post($this->tagStoreUrl($project, $task), ['name' => 'nope'])
            ->assertForbidden();
    }

    public function test_guest_cannot_attach_tag(): void
    {
        ['project' => $project, 'task' => $task] = $this->ownedTask();

        $this->post($this->tagStoreUrl($project, $task), ['name' => 'nope'])
            ->assertRedirect(route('login'));
    }

    public function test_whitespace_only_tag_name_is_rejected(): void
    {
        ['owner' => $owner, 'project' => $project, 'task' => $task] = $this->ownedTask();

        $this->actingAs($owner)
            ->from(route('projects.tasks.show', [$project, $task]))
            ->post($this->tagStoreUrl($project, $task), ['name' => '   '])
            ->assertSessionHasErrors('name');
    }

    public function test_tag_name_at_max_length_is_accepted(): void
    {
        ['owner' => $owner, 'project' => $project, 'task' => $task] = $this->ownedTask();
        $name = str_repeat('t', StoreTaskTagRequest::MAX_NAME_LENGTH);

        $this->actingAs($owner)
            ->post($this->tagStoreUrl($project, $task), ['name' => $name])
            ->assertRedirect();

        $this->assertDatabaseHas('tags', ['name' => $name, 'project_id' => $project->id]);
    }

    public function test_tag_name_over_max_length_is_rejected(): void
    {
        ['owner' => $owner, 'project' => $project, 'task' => $task] = $this->ownedTask();

        $this->actingAs($owner)
            ->from(route('projects.tasks.show', [$project, $task]))
            ->post($this->tagStoreUrl($project, $task), [
                'name' => str_repeat('t', StoreTaskTagRequest::MAX_NAME_LENGTH + 1),
            ])
            ->assertSessionHasErrors('name');
    }

    public function test_duplicate_tag_name_reuses_existing_tag_record(): void
    {
        ['owner' => $owner, 'project' => $project, 'task' => $task] = $this->ownedTask();
        $existing = $this->projectTag($project, ['name' => 'urgent', 'slug' => 'urgent']);

        $this->actingAs($owner)
            ->post($this->tagStoreUrl($project, $task), ['name' => 'URGENT'])
            ->assertRedirect();

        $this->assertDatabaseCount('tags', 1);
        $this->assertDatabaseHas('tag_task', [
            'task_id' => $task->id,
            'tag_id' => $existing->id,
        ]);
    }

    public function test_tag_slug_is_generated_from_name(): void
    {
        ['owner' => $owner, 'project' => $project, 'task' => $task] = $this->ownedTask();

        $this->actingAs($owner)
            ->post($this->tagStoreUrl($project, $task), ['name' => 'Needs Review'])
            ->assertRedirect();

        $this->assertDatabaseHas('tags', [
            'name' => 'Needs Review',
            'slug' => 'needs-review',
            'project_id' => $project->id,
        ]);
    }

    public function test_owner_can_attach_existing_tag_by_id(): void
    {
        ['owner' => $owner, 'project' => $project, 'task' => $task] = $this->ownedTask();
        $tag = $this->projectTag($project, ['name' => 'existing']);

        $this->actingAs($owner)
            ->post($this->tagStoreUrl($project, $task), ['tag_id' => $tag->id])
            ->assertRedirect();

        $this->assertDatabaseHas('tag_task', [
            'task_id' => $task->id,
            'tag_id' => $tag->id,
        ]);
    }

    public function test_attaching_same_tag_twice_does_not_duplicate_pivot(): void
    {
        ['owner' => $owner, 'project' => $project, 'task' => $task] = $this->ownedTask();
        $tag = $this->projectTag($project);

        $this->actingAs($owner)->post($this->tagStoreUrl($project, $task), ['tag_id' => $tag->id]);
        $this->actingAs($owner)->post($this->tagStoreUrl($project, $task), ['tag_id' => $tag->id]);

        $this->assertSame(1, $task->tags()->whereKey($tag->id)->count());
    }

    public function test_owner_can_detach_tag(): void
    {
        ['owner' => $owner, 'project' => $project, 'task' => $task] = $this->ownedTask();
        $tag = $this->projectTag($project);
        $task->tags()->attach($tag->id);

        $this->actingAs($owner)
            ->delete($this->tagDestroyUrl($project, $task, $tag))
            ->assertRedirect(route('projects.tasks.show', [$project, $task]));

        $this->assertDatabaseHas('tags', ['id' => $tag->id]);
        $this->assertDatabaseMissing('tag_task', [
            'task_id' => $task->id,
            'tag_id' => $tag->id,
        ]);
    }

    public function test_member_can_detach_tag(): void
    {
        ['member' => $member, 'project' => $project] = $this->sharedProject();
        $task = Task::factory()->for($project)->create();
        $tag = $this->projectTag($project);
        $task->tags()->attach($tag->id);

        $this->actingAs($member)
            ->delete($this->tagDestroyUrl($project, $task, $tag))
            ->assertRedirect();
    }

    public function test_outsider_cannot_detach_tag(): void
    {
        ['project' => $project, 'task' => $task] = $this->ownedTask();
        $tag = $this->projectTag($project);
        $task->tags()->attach($tag->id);
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->delete($this->tagDestroyUrl($project, $task, $tag))
            ->assertForbidden();
    }

    public function test_guest_cannot_detach_tag(): void
    {
        ['project' => $project, 'task' => $task] = $this->ownedTask();
        $tag = $this->projectTag($project);
        $task->tags()->attach($tag->id);

        $this->delete($this->tagDestroyUrl($project, $task, $tag))
            ->assertRedirect(route('login'));
    }

    public function test_detaching_unattached_tag_returns_not_found(): void
    {
        ['owner' => $owner, 'project' => $project, 'task' => $task] = $this->ownedTask();
        $tag = $this->projectTag($project);

        $this->actingAs($owner)
            ->delete($this->tagDestroyUrl($project, $task, $tag))
            ->assertNotFound();
    }

    public function test_tag_routes_return_not_found_for_wrong_project(): void
    {
        $owner = User::factory()->create();
        $projectA = Project::factory()->for($owner)->create();
        $projectB = Project::factory()->for($owner)->create();
        $taskOnB = Task::factory()->for($projectB)->create();
        $tag = $this->projectTag($projectB);

        $this->actingAs($owner)
            ->post($this->tagStoreUrl($projectA, $taskOnB), ['tag_id' => $tag->id])
            ->assertNotFound();
    }

    public function test_task_show_displays_attached_tags(): void
    {
        ['owner' => $owner, 'project' => $project, 'task' => $task] = $this->ownedTask();
        $tag = $this->projectTag($project, ['name' => 'visible-tag']);
        $task->tags()->attach($tag->id);

        $this->actingAs($owner)
            ->get(route('projects.tasks.show', [$project, $task]))
            ->assertOk()
            ->assertSee('visible-tag', false);
    }

    public function test_project_task_list_displays_task_tags(): void
    {
        ['owner' => $owner, 'project' => $project, 'task' => $task] = $this->ownedTask();
        $tag = $this->projectTag($project, ['name' => 'list-tag']);
        $task->tags()->attach($tag->id);

        $this->actingAs($owner)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('list-tag', false);
    }

    public function test_project_task_list_filters_by_tag(): void
    {
        ['owner' => $owner, 'project' => $project] = $this->ownedProject();
        $tagged = Task::factory()->for($project)->create(['title' => 'Tagged task']);
        $plain = Task::factory()->for($project)->create(['title' => 'Plain task']);
        $tag = $this->projectTag($project, ['name' => 'filterme', 'slug' => 'filterme']);
        $tagged->tags()->attach($tag->id);

        $this->actingAs($owner)
            ->get(route('projects.show', ['project' => $project, 'tag' => 'filterme']))
            ->assertOk()
            ->assertSee('Tagged task', false)
            ->assertDontSee('Plain task', false);
    }

    public function test_tag_filter_combines_with_status_and_search(): void
    {
        ['owner' => $owner, 'project' => $project] = $this->ownedProject();
        $match = Task::factory()->for($project)->todo()->create([
            'title' => 'Alpha tagged',
            'description' => 'needle',
        ]);
        $wrongStatus = Task::factory()->for($project)->completed()->create(['title' => 'Alpha tagged done']);
        $tag = $this->projectTag($project, ['slug' => 'combo']);
        $match->tags()->attach($tag->id);
        $wrongStatus->tags()->attach($tag->id);

        $this->actingAs($owner)
            ->get(route('projects.show', [
                'project' => $project,
                'tag' => 'combo',
                'status' => TaskStatus::Todo->value,
                'search' => 'needle',
            ]))
            ->assertOk()
            ->assertSee('Alpha tagged', false)
            ->assertDontSee('Alpha tagged done', false);
    }

    public function test_tag_filter_preserves_query_string_in_pagination(): void
    {
        ['owner' => $owner, 'project' => $project] = $this->ownedProject();
        $tag = $this->projectTag($project, ['slug' => 'paged']);

        foreach (range(1, 11) as $index) {
            $task = Task::factory()->for($project)->create(['title' => "Paged {$index}"]);
            $task->tags()->attach($tag->id);
        }

        $response = $this->actingAs($owner)->get(route('projects.show', [
            'project' => $project,
            'tag' => 'paged',
            'sort' => 'oldest',
            'page' => 2,
        ]));

        $response->assertOk();
        $this->assertStringContainsString('tag=paged', (string) $response->getContent());
        $this->assertStringContainsString('sort=oldest', (string) $response->getContent());
    }

    public function test_invalid_tag_filter_is_ignored_without_error(): void
    {
        ['owner' => $owner, 'project' => $project] = $this->ownedProject();
        Task::factory()->for($project)->create(['title' => 'Still visible']);

        $this->actingAs($owner)
            ->get(route('projects.show', ['project' => $project, 'tag' => 'not-a-real-tag']))
            ->assertOk()
            ->assertSee('Still visible', false);
    }

    public function test_outsider_cannot_filter_foreign_project_tasks_by_tag(): void
    {
        ['project' => $project] = $this->ownedProject();
        $task = Task::factory()->for($project)->create(['title' => 'Secret tagged']);
        $tag = $this->projectTag($project, ['slug' => 'secret']);
        $task->tags()->attach($tag->id);
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->get(route('projects.show', ['project' => $project, 'tag' => 'secret']))
            ->assertForbidden();
    }

    public function test_comments_still_work_after_tags_feature(): void
    {
        ['owner' => $owner, 'project' => $project, 'task' => $task] = $this->ownedTask();

        $this->actingAs($owner)
            ->post(route('projects.tasks.comments.store', [$project, $task]), ['body' => 'Still commenting'])
            ->assertRedirect();

        $this->assertDatabaseHas('comments', ['body' => 'Still commenting']);
    }

    public function test_tag_slug_collision_gets_numeric_suffix_within_project(): void
    {
        ['project' => $project] = $this->ownedProject();
        Tag::factory()->for($project)->create(['name' => 'First', 'slug' => 'dup']);
        $tag = Tag::findOrCreateForProject($project, 'dup');

        $this->assertSame('dup', $tag->name);
        $this->assertSame('dup-1', $tag->slug);
    }
}
