<?php

namespace Database\Seeders;

use App\Enums\ProjectRole;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Comment;
use App\Models\Project;
use App\Models\Tag;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\User;
use App\Support\ProjectSummaryCache;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
class WorkBoardDemoSeeder extends Seeder
{
    /**
     * @var list<string>
     */
    private const TAG_POOL = [
        'backend',
        'frontend',
        'bug',
        'feature',
        'urgent',
        'api',
        'database',
        'testing',
        'documentation',
        'performance',
        'security',
        'devops',
        'refactor',
        'ux',
        'mobile',
        'release',
        'design',
        'monitoring',
        'payments',
        'auth',
        'infra',
        'support',
    ];

    /**
     * @var list<array{name: string, description: string, tasks: list<string>}>
     */
    private const PROJECTS = [
        [
            'name' => 'Customer Portal Redesign',
            'description' => 'Modernize the customer-facing portal with improved navigation, accessibility, and self-service flows.',
            'tasks' => [
                'Redesign dashboard layout for mobile breakpoints',
                'Add customer profile validation rules',
                'Implement saved preferences API',
                'Improve onboarding checklist UX',
                'Add accessibility audit fixes for forms',
                'Migrate legacy portal widgets to new components',
            ],
        ],
        [
            'name' => 'Billing API Migration',
            'description' => 'Move billing integrations to a versioned REST API with stronger validation and observability.',
            'tasks' => [
                'Add invoice endpoint validation',
                'Implement retry handling for failed payments',
                'Add webhook signature verification',
                'Optimize transaction lookup query',
                'Expand payment API test coverage',
                'Document billing error response codes',
            ],
        ],
        [
            'name' => 'Mobile App Backend',
            'description' => 'Support mobile clients with reliable sync, push-friendly payloads, and offline-friendly endpoints.',
            'tasks' => [
                'Add device registration endpoint',
                'Implement push notification preferences',
                'Optimize task list payload size',
                'Add pagination cursors for mobile feeds',
                'Harden mobile auth token refresh flow',
            ],
        ],
        [
            'name' => 'Reporting Dashboard',
            'description' => 'Deliver project and task analytics for owners and members with exportable summaries.',
            'tasks' => [
                'Build project activity summary cards',
                'Add CSV export for task metrics',
                'Cache expensive reporting aggregates',
                'Add overdue task trend chart',
                'Validate report filter parameters',
            ],
        ],
        [
            'name' => 'Authentication Improvements',
            'description' => 'Strengthen login flows, session handling, and API token security for WorkBoard.',
            'tasks' => [
                'Review Sanctum token expiration policy',
                'Add login rate limiting alerts',
                'Improve password reset messaging',
                'Audit project membership authorization paths',
                'Add session timeout documentation',
            ],
        ],
        [
            'name' => 'Performance Optimization',
            'description' => 'Reduce page load times and database load across project and task listings.',
            'tasks' => [
                'Profile project summary query hotspots',
                'Add indexes for task filter columns',
                'Reduce N+1 queries on task show page',
                'Tune Redis cache TTL for summaries',
                'Benchmark API task list pagination',
            ],
        ],
        [
            'name' => 'Notification System',
            'description' => 'Refine in-app notifications for tasks, comments, and project membership changes.',
            'tasks' => [
                'Review notification recipient rules',
                'Add unread badge performance check',
                'Improve stale notification fallback copy',
                'Queue notification listener monitoring',
                'Document notification open flow',
            ],
        ],
        [
            'name' => 'Internal Admin Panel',
            'description' => 'Give support staff tools to inspect projects, members, and task activity safely.',
            'tasks' => [
                'Add read-only project inspection view',
                'List recent comments across projects',
                'Add member role change audit log',
                'Restrict admin routes with policies',
            ],
        ],
        [
            'name' => 'Data Export Service',
            'description' => 'Allow project owners to export tasks, tags, and comments for compliance and backups.',
            'tasks' => [
                'Design export JSON schema',
                'Implement project export endpoint',
                'Add export job progress tracking',
                'Validate export authorization rules',
                'Write export service documentation',
            ],
        ],
        [
            'name' => 'Infrastructure Upgrade',
            'description' => 'Prepare Docker, Redis, and queue workers for smoother local and staging environments.',
            'tasks' => [
                'Document Redis queue worker setup',
                'Upgrade PHP extension build steps',
                'Add health check for Redis connectivity',
                'Review MySQL backup procedure',
                'Automate storage permission checks',
            ],
        ],
        [
            'name' => 'QA Automation',
            'description' => 'Expand automated coverage for API, policies, and critical WorkBoard workflows.',
            'tasks' => [
                'Add API regression suite for tags',
                'Cover notification navigation edge cases',
                'Add Postman smoke test checklist',
                'Stabilize flaky listing filter tests',
                'Document local demo seed reset steps',
            ],
        ],
    ];

    private const GENERIC_TASK_TITLES = [
        'Clarify acceptance criteria with stakeholders',
        'Update technical notes after review',
        'Refine error handling for edge cases',
        'Add follow-up items from sprint retro',
        'Verify permissions for member actions',
        'Prepare release notes draft',
    ];

    public function run(): void
    {
        $userOne = DemoUserSeeder::$userOne ?? User::query()->where('email', config('demo.user_one.email'))->firstOrFail();
        $userTwo = DemoUserSeeder::$userTwo ?? User::query()->where('email', config('demo.user_two.email'))->firstOrFail();
        $teamMembers = DemoTeamUserSeeder::$teamMembers ?? collect();

        foreach (self::PROJECTS as $index => $definition) {
            $owner = $index % 2 === 0 ? $userOne : $userTwo;
            $collaborator = $owner->id === $userOne->id ? $userTwo : $userOne;

            $project = Project::withoutEvents(function () use ($owner, $definition): Project {
                return Project::factory()->for($owner)->create([
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                ]);
            });

            $this->seedProjectMembership($project, $owner, $collaborator, $teamMembers);

            $memberIds = $this->projectMemberIds($project);
            $tags = $this->seedProjectTags($project);
            $this->seedTasksForProject($project, $definition, $memberIds, $tags);

            ProjectSummaryCache::forget($project);
        }
    }

    /**
     * @param  Collection<int, User>  $teamMembers
     */
    private function seedProjectMembership(
        Project $project,
        User $owner,
        User $collaborator,
        Collection $teamMembers,
    ): void {
        if ($collaborator->id !== $owner->id) {
            $project->members()->syncWithoutDetaching([
                $collaborator->id => ['role' => ProjectRole::Member->value],
            ]);
        }

        if ($teamMembers->isEmpty()) {
            return;
        }

        $extraCount = random_int(2, min(4, $teamMembers->count()));
        $teamMembers
            ->shuffle()
            ->take($extraCount)
            ->each(function (User $user) use ($project, $owner): void {
                if ($user->id === $owner->id) {
                    return;
                }

                $project->members()->syncWithoutDetaching([
                    $user->id => ['role' => ProjectRole::Member->value],
                ]);
            });
    }

    /**
     * @return Collection<int, int>
     */
    private function projectMemberIds(Project $project): Collection
    {
        $ids = collect([$project->user_id]);

        return $ids->merge(
            $project->members()->pluck('users.id'),
        )->unique()->values();
    }

    /**
     * @return Collection<int, Tag>
     */
    private function seedProjectTags(Project $project): Collection
    {
        $names = collect(self::TAG_POOL)->shuffle()->take(random_int(10, 12));

        return $names->map(function (string $name) use ($project): Tag {
            return Tag::withoutEvents(function () use ($project, $name): Tag {
                $slug = Tag::generateUniqueSlugForProject($project, \Illuminate\Support\Str::slug($name));

                return Tag::query()->create([
                    'project_id' => $project->id,
                    'name' => $name,
                    'slug' => $slug,
                ]);
            });
        });
    }

    /**
     * @param  Collection<int, int>  $memberIds
     * @param  Collection<int, Tag>  $tags
     */
    private function seedTasksForProject(
        Project $project,
        array $definition,
        Collection $memberIds,
        Collection $tags,
    ): void {
        $taskCount = random_int(14, 22);
        $titles = collect($definition['tasks'])->shuffle();

        while ($titles->count() < $taskCount) {
            $titles->push(self::GENERIC_TASK_TITLES[array_rand(self::GENERIC_TASK_TITLES)]);
        }

        foreach ($titles->take($taskCount) as $title) {
            $status = fake()->randomElement(TaskStatus::cases());
            $priority = fake()->randomElement(TaskPriority::cases());
            $assignee = $this->pickAssignee($memberIds);

            $task = Task::withoutEvents(function () use ($project, $title, $status, $priority, $assignee): Task {
                return Task::factory()
                    ->for($project)
                    ->state([
                        'title' => $title,
                        'description' => fake()->optional(0.85)->paragraph(),
                        'status' => $status,
                        'priority' => $priority,
                        'due_date' => $this->randomDueDate(),
                        'assigned_to' => $assignee,
                    ])
                    ->create();
            });

            $this->attachTagsToTask($task, $tags);
            $this->seedCommentsForTask($task, $memberIds);
            $this->seedAttachmentsForTask($project, $task, $memberIds);
        }
    }

    /**
     * @param  Collection<int, int>  $memberIds
     */
    private function pickAssignee(Collection $memberIds): ?int
    {
        if (fake()->boolean(25)) {
            return null;
        }

        return $memberIds->random();
    }

    private function randomDueDate(): ?string
    {
        $roll = random_int(1, 100);

        if ($roll <= 15) {
            return null;
        }

        if ($roll <= 35) {
            return now()->subDays(random_int(1, 21))->toDateString();
        }

        if ($roll <= 60) {
            return now()->addDays(random_int(1, 7))->toDateString();
        }

        return now()->addDays(random_int(14, 90))->toDateString();
    }

    /**
     * @param  Collection<int, Tag>  $tags
     */
    private function attachTagsToTask(Task $task, Collection $tags): void
    {
        if ($tags->isEmpty() || fake()->boolean(8)) {
            return;
        }

        $max = min(4, $tags->count());
        $count = fake()->boolean(35) ? 1 : random_int(2, $max);
        $task->tags()->syncWithoutDetaching(
            $tags->random($count)->pluck('id')->all(),
        );
    }

    /**
     * @param  Collection<int, int>  $memberIds
     */
    private function seedCommentsForTask(Task $task, Collection $memberIds): void
    {
        $commentCount = fake()->boolean(12)
            ? 0
            : fake()->numberBetween(2, 8);

        for ($i = 0; $i < $commentCount; $i++) {
            Comment::withoutEvents(function () use ($task, $memberIds): void {
                Comment::factory()
                    ->for($task)
                    ->for(User::query()->findOrFail($memberIds->random()))
                    ->realistic()
                    ->create();
            });
        }
    }

    /**
     * @param  Collection<int, int>  $memberIds
     */
    private function seedAttachmentsForTask(Project $project, Task $task, Collection $memberIds): void
    {
        if (fake()->boolean(40)) {
            return;
        }

        $attachmentCount = fake()->boolean(75) ? 1 : 2;

        for ($i = 0; $i < $attachmentCount; $i++) {
            $fixture = fake()->randomElement([
                ['original_name' => 'meeting-notes.txt', 'mime_type' => 'text/plain', 'extension' => 'txt'],
                ['original_name' => 'wireframe.png', 'mime_type' => 'image/png', 'extension' => 'png'],
                ['original_name' => 'requirements.pdf', 'mime_type' => 'application/pdf', 'extension' => 'pdf'],
                ['original_name' => 'api-sample.json', 'mime_type' => 'text/plain', 'extension' => 'txt'],
            ]);

            $uploaderId = $memberIds->random();
            $directory = sprintf('task-attachments/%d/%d', $project->id, $task->id);
            $path = $directory.'/'.Str::uuid()->toString().'.'.$fixture['extension'];
            $contents = $this->demoAttachmentContents($fixture['mime_type']);
            $disk = Storage::disk(TaskAttachment::STORAGE_DISK);
            $disk->put($path, $contents);

            TaskAttachment::query()->create([
                'task_id' => $task->id,
                'user_id' => $uploaderId,
                'original_name' => $fixture['original_name'],
                'path' => $path,
                'mime_type' => $fixture['mime_type'],
                'size' => strlen($contents),
            ]);
        }
    }

    private function demoAttachmentContents(string $mimeType): string
    {
        return match ($mimeType) {
            'image/png' => base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', true) ?: 'png',
            'application/pdf' => '%PDF-1.4 WorkBoard demo attachment',
            default => "WorkBoard demo attachment\nProject task export sample.\n",
        };
    }
}
