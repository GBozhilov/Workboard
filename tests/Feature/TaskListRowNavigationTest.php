<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Tag;
use App\Models\Task;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskListRowNavigationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<int, array{href: string, task_id: string, title: string}>
     */
    private function extractTaskListRowLinks(string $html): array
    {
        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);
        $anchors = $xpath->query('//a[@data-task-row-link]');

        $rows = [];
        if ($anchors === false) {
            return $rows;
        }

        foreach ($anchors as $anchor) {
            $href = $anchor->attributes?->getNamedItem('href')?->nodeValue ?? '';
            $taskId = $anchor->attributes?->getNamedItem('data-task-id')?->nodeValue ?? '';
            $title = $anchor->attributes?->getNamedItem('data-task-row-title')?->nodeValue ?? '';

            $rows[] = [
                'href' => $href,
                'task_id' => $taskId,
                'title' => trim($title),
            ];
        }

        return $rows;
    }

    public function test_each_task_list_row_links_to_its_own_task_show_page(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $taskA = Task::factory()->for($project)->todo()->create(['title' => 'List Row Alpha']);
        $taskB = Task::factory()->for($project)->todo()->create([
            'title' => 'List Row Beta',
            'due_date' => now()->addWeek(),
        ]);
        $taskC = Task::factory()->for($project)->todo()->create(['title' => 'List Row Gamma']);

        $html = $this->actingAs($user)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->getContent();

        $rows = $this->extractTaskListRowLinks($html);

        $this->assertCount(3, $rows);
        $this->assertDatabaseCount('tasks', 3);

        foreach ($rows as $row) {
            $task = Task::query()->findOrFail($row['task_id']);
            $expectedUrl = route('projects.tasks.show', [
                'project' => $project->getKey(),
                'task' => $task->getKey(),
            ]);

            $this->assertSame($expectedUrl, $row['href']);
            $this->assertSame($task->title, $row['title']);
            $this->assertStringEndsWith('/tasks/'.$task->getKey(), $row['href']);
        }

        $hrefs = array_column($rows, 'href');
        $this->assertCount(3, array_unique($hrefs));

        $this->assertSame(
            [$taskA->getKey(), $taskB->getKey(), $taskC->getKey()],
            collect($rows)->pluck('task_id')->map(fn (string $id) => (int) $id)->sort()->values()->all()
        );
    }

    public function test_task_with_tags_still_links_to_itself_not_sibling_tasks(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $plain = Task::factory()->for($project)->todo()->create(['title' => 'Plain list row']);
        $tagged = Task::factory()->for($project)->todo()->create(['title' => 'Tagged list row']);
        $tag = Tag::factory()->for($project)->create(['name' => 'be']);
        $tagged->tags()->attach($tag->id);

        $html = $this->actingAs($user)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->getContent();

        $rows = $this->extractTaskListRowLinks($html);
        $byTitle = [];
        foreach ($rows as $row) {
            $byTitle[$row['title']] = $row;
        }

        $plainRow = $byTitle['Plain list row'];
        $taggedRow = $byTitle['Tagged list row'];

        $this->assertSame((string) $plain->getKey(), $plainRow['task_id']);
        $this->assertSame((string) $tagged->getKey(), $taggedRow['task_id']);
        $this->assertStringEndsWith('/tasks/'.$plain->getKey(), $plainRow['href']);
        $this->assertStringEndsWith('/tasks/'.$tagged->getKey(), $taggedRow['href']);
        $this->assertNotSame($plainRow['href'], $taggedRow['href']);
    }

    public function test_task_list_row_links_are_not_a_single_shared_stretched_overlay(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        foreach (['One', 'Two', 'Three'] as $title) {
            Task::factory()->for($project)->todo()->create(['title' => $title]);
        }

        $html = $this->actingAs($user)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->getContent();

        $rows = $this->extractTaskListRowLinks($html);
        $this->assertCount(3, $rows);

        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);
        $anchors = $xpath->query('//a[@data-task-row-link]');
        $this->assertNotFalse($anchors);

        foreach ($anchors as $anchor) {
            $class = $anchor->attributes?->getNamedItem('class')?->nodeValue ?? '';
            $this->assertStringNotContainsString('absolute', $class);
            $this->assertStringNotContainsString('inset-0', $class);
            $this->assertStringNotContainsString('pointer-events-none', $class);
        }
    }
}
