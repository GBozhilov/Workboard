<?php

namespace Tests\Feature;

use App\Http\Requests\StoreTaskAttachmentRequest;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesWorkBoardFixtures;
use Tests\TestCase;

class TaskAttachmentTest extends TestCase
{
    use CreatesWorkBoardFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(TaskAttachment::STORAGE_DISK);
    }

    private function attachmentStoreUrl(Project $project, Task $task): string
    {
        return route('projects.tasks.attachments.store', [$project, $task]);
    }

    private function attachmentDownloadUrl(Project $project, Task $task, TaskAttachment $attachment): string
    {
        return route('projects.tasks.attachments.download', [$project, $task, $attachment]);
    }

    private function attachmentDestroyUrl(Project $project, Task $task, TaskAttachment $attachment): string
    {
        return route('projects.tasks.attachments.destroy', [$project, $task, $attachment]);
    }

    private function storeFakeFile(
        Project $project,
        Task $task,
        User $user,
        UploadedFile $file,
        string $originalName = 'upload.bin',
    ): TaskAttachment {
        $this->actingAs($user)
            ->post($this->attachmentStoreUrl($project, $task), [
                'file' => $file,
            ], ['CONTENT_TYPE' => 'multipart/form-data'])
            ->assertRedirect(route('projects.tasks.show', [$project, $task]));

        return TaskAttachment::query()->where('task_id', $task->id)->latest('id')->firstOrFail();
    }

    public function test_task_has_many_attachments(): void
    {
        ['project' => $project, 'task' => $task] = $this->ownedTask();
        $attachment = TaskAttachment::factory()->for($task)->create();

        $this->assertTrue($task->attachments->contains($attachment));
    }

    public function test_attachment_belongs_to_task_and_uploader(): void
    {
        $user = User::factory()->create();
        ['project' => $project, 'task' => $task] = $this->ownedTask();
        $attachment = TaskAttachment::factory()->for($task)->for($user)->create();

        $this->assertTrue($attachment->task->is($task));
        $this->assertTrue($attachment->user->is($user));
        $this->assertTrue($user->taskAttachments->contains($attachment));
    }

    public function test_project_owner_can_upload_attachment(): void
    {
        ['owner' => $owner, 'project' => $project, 'task' => $task] = $this->ownedTask();
        $file = UploadedFile::fake()->create('spec.pdf', 120, 'application/pdf');

        $attachment = $this->storeFakeFile($project, $task, $owner, $file, 'spec.pdf');

        $this->assertDatabaseHas('task_attachments', [
            'id' => $attachment->id,
            'task_id' => $task->id,
            'user_id' => $owner->id,
            'original_name' => 'spec.pdf',
            'mime_type' => 'application/pdf',
        ]);
        Storage::disk(TaskAttachment::STORAGE_DISK)->assertExists($attachment->path);
    }

    public function test_project_member_can_upload_attachment(): void
    {
        ['member' => $member, 'project' => $project] = $this->sharedProject();
        $task = Task::factory()->for($project)->create();
        $file = UploadedFile::fake()->create('photo.png', 10, 'image/png');

        $this->storeFakeFile($project, $task, $member, $file);

        $this->assertDatabaseCount('task_attachments', 1);
    }

    public function test_outsider_cannot_upload_attachment(): void
    {
        ['project' => $project, 'task' => $task] = $this->ownedTask();
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->post($this->attachmentStoreUrl($project, $task), [
                'file' => UploadedFile::fake()->create('nope.pdf', 50, 'application/pdf'),
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('task_attachments', 0);
    }

    public function test_guest_cannot_upload_attachment(): void
    {
        ['project' => $project, 'task' => $task] = $this->ownedTask();

        $this->post($this->attachmentStoreUrl($project, $task), [
            'file' => UploadedFile::fake()->create('nope.pdf', 50, 'application/pdf'),
        ])->assertRedirect(route('login'));
    }

    public function test_upload_requires_a_file(): void
    {
        ['owner' => $owner, 'project' => $project, 'task' => $task] = $this->ownedTask();

        $this->actingAs($owner)
            ->from(route('projects.tasks.show', [$project, $task]))
            ->post($this->attachmentStoreUrl($project, $task), [])
            ->assertSessionHasErrors('file');
    }

    public function test_upload_accepts_pdf_text_and_image_types(): void
    {
        ['owner' => $owner, 'project' => $project, 'task' => $task] = $this->ownedTask();

        foreach ([
            UploadedFile::fake()->create('notes.pdf', 40, 'application/pdf'),
            UploadedFile::fake()->create('readme.txt', 10, 'text/plain'),
            UploadedFile::fake()->create('shot.jpg', 8, 'image/jpeg'),
        ] as $file) {
            $this->actingAs($owner)
                ->post($this->attachmentStoreUrl($project, $task), ['file' => $file])
                ->assertRedirect();
        }

        $this->assertDatabaseCount('task_attachments', 3);
    }

    public function test_upload_rejects_oversized_file(): void
    {
        ['owner' => $owner, 'project' => $project, 'task' => $task] = $this->ownedTask();
        $kilobytes = StoreTaskAttachmentRequest::MAX_FILE_SIZE_KB + 1;

        $this->actingAs($owner)
            ->from(route('projects.tasks.show', [$project, $task]))
            ->post($this->attachmentStoreUrl($project, $task), [
                'file' => UploadedFile::fake()->create('big.pdf', $kilobytes, 'application/pdf'),
            ])
            ->assertSessionHasErrors('file');
    }

    public function test_upload_rejects_unsupported_executable_type(): void
    {
        ['owner' => $owner, 'project' => $project, 'task' => $task] = $this->ownedTask();

        $this->actingAs($owner)
            ->from(route('projects.tasks.show', [$project, $task]))
            ->post($this->attachmentStoreUrl($project, $task), [
                'file' => UploadedFile::fake()->create('script.php', 20, 'application/x-php'),
            ])
            ->assertSessionHasErrors('file');
    }

    public function test_duplicate_original_filenames_use_distinct_storage_paths(): void
    {
        ['owner' => $owner, 'project' => $project, 'task' => $task] = $this->ownedTask();
        $first = $this->storeFakeFile($project, $task, $owner, UploadedFile::fake()->create('report.pdf', 30, 'application/pdf'));
        $second = $this->storeFakeFile($project, $task, $owner, UploadedFile::fake()->create('report.pdf', 40, 'application/pdf'));

        $this->assertNotSame($first->path, $second->path);
        Storage::disk(TaskAttachment::STORAGE_DISK)->assertExists($first->path);
        Storage::disk(TaskAttachment::STORAGE_DISK)->assertExists($second->path);
    }

    public function test_unusual_filename_is_stored_safely(): void
    {
        ['owner' => $owner, 'project' => $project, 'task' => $task] = $this->ownedTask();
        $attachment = $this->storeFakeFile(
            $project,
            $task,
            $owner,
            UploadedFile::fake()->create('../../../etc/passwd', 12, 'text/plain'),
        );

        $this->assertSame('passwd', $attachment->original_name);
        $this->assertStringStartsWith('task-attachments/'.$project->id.'/'.$task->id.'/', $attachment->path);
        $this->assertStringNotContainsString('..', $attachment->path);
    }

    public function test_owner_and_member_can_download_attachment(): void
    {
        ['owner' => $owner, 'member' => $member, 'project' => $project] = $this->sharedProject();
        $task = Task::factory()->for($project)->create();
        $attachment = $this->storeFakeFile(
            $project,
            $task,
            $owner,
            UploadedFile::fake()->create('download-me.pdf', 25, 'application/pdf'),
        );

        $this->actingAs($owner)
            ->get($this->attachmentDownloadUrl($project, $task, $attachment))
            ->assertOk()
            ->assertDownload('download-me.pdf');

        $this->actingAs($member)
            ->get($this->attachmentDownloadUrl($project, $task, $attachment))
            ->assertOk();
    }

    public function test_outsider_and_guest_cannot_download_attachment(): void
    {
        ['owner' => $owner, 'project' => $project, 'task' => $task] = $this->ownedTask();
        $attachment = $this->storeFakeFile(
            $project,
            $task,
            $owner,
            UploadedFile::fake()->create('secret.pdf', 25, 'application/pdf'),
        );
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->get($this->attachmentDownloadUrl($project, $task, $attachment))
            ->assertForbidden();

        auth()->logout();

        $this->get($this->attachmentDownloadUrl($project, $task, $attachment))
            ->assertRedirect(route('login'));
    }

    public function test_download_returns_not_found_when_physical_file_missing(): void
    {
        ['owner' => $owner, 'project' => $project, 'task' => $task] = $this->ownedTask();
        $attachment = TaskAttachment::factory()->for($task)->for($owner)->create([
            'path' => 'task-attachments/missing/file.pdf',
        ]);

        $this->actingAs($owner)
            ->get($this->attachmentDownloadUrl($project, $task, $attachment))
            ->assertNotFound();
    }

    public function test_uploader_can_delete_own_attachment(): void
    {
        ['member' => $member, 'project' => $project] = $this->sharedProject();
        $task = Task::factory()->for($project)->create();
        $attachment = $this->storeFakeFile(
            $project,
            $task,
            $member,
            UploadedFile::fake()->create('mine.pdf', 20, 'application/pdf'),
        );

        $this->actingAs($member)
            ->delete($this->attachmentDestroyUrl($project, $task, $attachment))
            ->assertRedirect(route('projects.tasks.show', [$project, $task]));

        $this->assertDatabaseMissing('task_attachments', ['id' => $attachment->id]);
        Storage::disk(TaskAttachment::STORAGE_DISK)->assertMissing($attachment->path);
    }

    public function test_project_owner_can_delete_another_users_attachment(): void
    {
        ['owner' => $owner, 'member' => $member, 'project' => $project] = $this->sharedProject();
        $task = Task::factory()->for($project)->create();
        $attachment = $this->storeFakeFile(
            $project,
            $task,
            $member,
            UploadedFile::fake()->create('member.pdf', 20, 'application/pdf'),
        );

        $this->actingAs($owner)
            ->delete($this->attachmentDestroyUrl($project, $task, $attachment))
            ->assertRedirect();

        $this->assertDatabaseMissing('task_attachments', ['id' => $attachment->id]);
    }

    public function test_member_cannot_delete_another_users_attachment(): void
    {
        ['owner' => $owner, 'member' => $member, 'project' => $project] = $this->sharedProject();
        $task = Task::factory()->for($project)->create();
        $attachment = $this->storeFakeFile(
            $project,
            $task,
            $owner,
            UploadedFile::fake()->create('owner.pdf', 20, 'application/pdf'),
        );

        $this->actingAs($member)
            ->delete($this->attachmentDestroyUrl($project, $task, $attachment))
            ->assertForbidden();

        $this->assertDatabaseHas('task_attachments', ['id' => $attachment->id]);
    }

    public function test_nested_attachment_from_another_task_returns_not_found(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $taskA = Task::factory()->for($project)->create();
        $taskB = Task::factory()->for($project)->create();
        $attachmentOnB = $this->storeFakeFile(
            $project,
            $taskB,
            $owner,
            UploadedFile::fake()->create('b.pdf', 20, 'application/pdf'),
        );

        $this->actingAs($owner)
            ->get($this->attachmentDownloadUrl($project, $taskA, $attachmentOnB))
            ->assertNotFound();

        $this->actingAs($owner)
            ->delete($this->attachmentDestroyUrl($project, $taskA, $attachmentOnB))
            ->assertNotFound();
    }

    public function test_attachment_from_another_project_cannot_be_accessed(): void
    {
        $owner = User::factory()->create();
        $projectA = Project::factory()->for($owner)->create();
        $projectB = Project::factory()->for($owner)->create();
        $taskOnB = Task::factory()->for($projectB)->create();
        $attachment = $this->storeFakeFile(
            $projectB,
            $taskOnB,
            $owner,
            UploadedFile::fake()->create('foreign.pdf', 20, 'application/pdf'),
        );

        $this->actingAs($owner)
            ->get($this->attachmentDownloadUrl($projectA, $taskOnB, $attachment))
            ->assertNotFound();
    }

    public function test_deleting_task_removes_attachment_records_and_files(): void
    {
        ['owner' => $owner, 'project' => $project, 'task' => $task] = $this->ownedTask();
        $attachment = $this->storeFakeFile(
            $project,
            $task,
            $owner,
            UploadedFile::fake()->create('task-file.pdf', 30, 'application/pdf'),
        );
        $path = $attachment->path;

        $this->actingAs($owner)
            ->delete(route('projects.tasks.destroy', [$project, $task]))
            ->assertRedirect(route('projects.show', $project));

        $this->assertDatabaseMissing('task_attachments', ['id' => $attachment->id]);
        Storage::disk(TaskAttachment::STORAGE_DISK)->assertMissing($path);
    }

    public function test_deleting_project_removes_attachment_records_and_files(): void
    {
        ['owner' => $owner, 'project' => $project] = $this->ownedProject();
        $task = Task::factory()->for($project)->create();
        $attachment = $this->storeFakeFile(
            $project,
            $task,
            $owner,
            UploadedFile::fake()->create('project-file.pdf', 30, 'application/pdf'),
        );
        $path = $attachment->path;

        $this->actingAs($owner)
            ->delete(route('projects.destroy', $project))
            ->assertRedirect(route('projects.index'));

        $this->assertDatabaseMissing('task_attachments', ['id' => $attachment->id]);
        Storage::disk(TaskAttachment::STORAGE_DISK)->assertMissing($path);
    }

    public function test_task_show_displays_attachment_metadata_and_actions(): void
    {
        ['owner' => $owner, 'member' => $member, 'project' => $project] = $this->sharedProject();
        $task = Task::factory()->for($project)->create();
        $attachment = $this->storeFakeFile(
            $project,
            $task,
            $member,
            UploadedFile::fake()->create('visible.pdf', 1, 'application/pdf'),
        );

        $attachment->refresh();

        $ownerResponse = $this->actingAs($owner)
            ->get(route('projects.tasks.show', [$project, $task]))
            ->assertOk()
            ->assertSee('visible.pdf', false)
            ->assertSee($member->name, false)
            ->assertSee($attachment->humanReadableSize(), false)
            ->assertSee('Download', false)
            ->assertSee('Delete', false);

        $this->assertStringNotContainsString($attachment->path, (string) $ownerResponse->getContent());

        $this->actingAs($member)
            ->get(route('projects.tasks.show', [$project, $task]))
            ->assertOk()
            ->assertSee('Delete', false);

        $otherMember = User::factory()->create();
        $this->attachProjectMember($project, $otherMember);

        $this->actingAs($otherMember)
            ->get(route('projects.tasks.show', [$project, $task]))
            ->assertOk()
            ->assertSee('Download', false)
            ->assertDontSee('data-confirm-message="Delete this file attachment?"', false);
    }

    public function test_comments_and_tags_still_work_after_attachments_feature(): void
    {
        ['owner' => $owner, 'project' => $project, 'task' => $task] = $this->ownedTask();

        $this->actingAs($owner)
            ->post(route('projects.tasks.comments.store', [$project, $task]), ['body' => 'Still commenting'])
            ->assertRedirect();

        $this->actingAs($owner)
            ->post(route('projects.tasks.tags.store', [$project, $task]), ['name' => 'still-tagging'])
            ->assertRedirect();

        $this->assertDatabaseHas('comments', ['body' => 'Still commenting']);
        $this->assertDatabaseHas('tags', ['name' => 'still-tagging', 'project_id' => $project->id]);
    }
}
