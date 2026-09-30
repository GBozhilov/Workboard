<?php

namespace Tests\Feature;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Comment;
use App\Models\Project;
use App\Models\Tag;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DemoDatasetSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_users_are_created_with_valid_hashed_passwords(): void
    {
        $this->seed(DatabaseSeeder::class);

        $userOne = DemoUserSeeder::$userOne;
        $userTwo = DemoUserSeeder::$userTwo;

        $this->assertNotNull($userOne);
        $this->assertNotNull($userTwo);
        $this->assertSame(config('demo.user_one.email'), $userOne->email);
        $this->assertSame(config('demo.user_two.email'), $userTwo->email);
        $this->assertSame(config('demo.user_one.name'), $userOne->name);
        $this->assertSame(config('demo.user_two.name'), $userTwo->name);
        $this->assertTrue(Hash::check(config('demo.user_one.password'), $userOne->password));
        $this->assertTrue(Hash::check(config('demo.user_two.password'), $userTwo->password));
        $this->assertSame(7, User::query()->count());
        $this->assertSame(5, User::query()->where('email', 'like', '%@workboard.demo')->count());
    }

    public function test_demo_dataset_has_realistic_projects_tasks_and_relationships(): void
    {
        $this->seed(DatabaseSeeder::class);

        $userOne = User::query()->where('email', config('demo.user_one.email'))->firstOrFail();
        $userTwo = User::query()->where('email', config('demo.user_two.email'))->firstOrFail();

        $projectCount = Project::query()->count();
        $this->assertGreaterThanOrEqual(10, $projectCount);
        $this->assertLessThanOrEqual(12, $projectCount);

        $taskCount = Task::query()->count();
        $this->assertGreaterThanOrEqual(150, $taskCount);
        $this->assertLessThanOrEqual(250, $taskCount);

        $this->assertGreaterThanOrEqual(400, Comment::query()->count());

        $tasksWithComments = Task::query()->whereHas('comments')->count();
        $this->assertGreaterThanOrEqual((int) ($taskCount * 0.85), $tasksWithComments);

        $this->assertGreaterThanOrEqual(80, TaskAttachment::query()->count());
        TaskAttachment::query()->each(function (TaskAttachment $attachment): void {
            $this->assertTrue(Storage::disk(TaskAttachment::STORAGE_DISK)->exists($attachment->path));
        });

        $ownedByOne = Project::query()->where('user_id', $userOne->id)->count();
        $ownedByTwo = Project::query()->where('user_id', $userTwo->id)->count();
        $this->assertGreaterThan(0, $ownedByOne);
        $this->assertGreaterThan(0, $ownedByTwo);

        $sharedForOne = Project::query()
            ->where('user_id', $userTwo->id)
            ->whereHas('members', fn ($q) => $q->whereKey($userOne->id))
            ->count();
        $sharedForTwo = Project::query()
            ->where('user_id', $userOne->id)
            ->whereHas('members', fn ($q) => $q->whereKey($userTwo->id))
            ->count();
        $this->assertGreaterThan(0, $sharedForOne);
        $this->assertGreaterThan(0, $sharedForTwo);

        Task::query()->with('project.members', 'project.user', 'assignee')->each(function (Task $task): void {
            $this->assertContains($task->status, TaskStatus::cases());
            $this->assertContains($task->priority, TaskPriority::cases());

            if ($task->assigned_to === null) {
                return;
            }

            $memberIds = collect([$task->project->user_id])
                ->merge($task->project->members->modelKeys())
                ->unique();

            $this->assertTrue(
                $memberIds->contains($task->assigned_to),
                'Task assignee must be the project owner or a member.',
            );
        });

        Comment::query()->with('task.project.members', 'task.project.user', 'user')->each(function (Comment $comment): void {
            $this->assertNotNull($comment->task);
            $this->assertNotNull($comment->user);

            $memberIds = collect([$comment->task->project->user_id])
                ->merge($comment->task->project->members->modelKeys())
                ->unique();

            $this->assertTrue($memberIds->contains($comment->user_id));
        });

        Project::query()->withCount('members')->each(function (Project $project): void {
            $this->assertGreaterThanOrEqual(4, $project->members_count);
        });

        Project::query()->with('tags')->each(function (Project $project): void {
            $tagCount = $project->tags->count();
            $this->assertGreaterThanOrEqual(10, $tagCount);
            $this->assertLessThanOrEqual(12, $tagCount);

            $project->tags->each(function (Tag $tag) use ($project): void {
                $this->assertSame($project->id, $tag->project_id);
            });
        });

        DB::table('tag_task')
            ->join('tasks', 'tasks.id', '=', 'tag_task.task_id')
            ->join('tags', 'tags.id', '=', 'tag_task.tag_id')
            ->select('tasks.project_id as task_project_id', 'tags.project_id as tag_project_id')
            ->get()
            ->each(function (object $row): void {
                $this->assertSame($row->task_project_id, $row->tag_project_id);
            });

        $this->assertSame(0, DB::table('notifications')->count());
        $this->assertSame(0, DB::table('jobs')->count());
    }

    public function test_demo_user_seeder_exposes_users_for_follow_up_seeders(): void
    {
        $this->seed(DemoUserSeeder::class);

        $this->assertInstanceOf(User::class, DemoUserSeeder::$userOne);
        $this->assertInstanceOf(User::class, DemoUserSeeder::$userTwo);
        $this->assertSame(config('demo.user_one.email'), DemoUserSeeder::$userOne->email);
        $this->assertSame(config('demo.user_two.email'), DemoUserSeeder::$userTwo->email);
    }

    public function test_demo_user_seeder_fails_when_credentials_are_missing(): void
    {
        config(['demo.user_one.email' => '']);

        $this->expectException(\RuntimeException::class);

        $this->seed(DemoUserSeeder::class);
    }
}
