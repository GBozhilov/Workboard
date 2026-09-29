<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesWorkBoardFixtures;
use Tests\TestCase;

class TaskAttachmentPreviewTest extends TestCase
{
    use CreatesWorkBoardFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(TaskAttachment::STORAGE_DISK);
    }

    private function previewUrl(Project $project, Task $task, TaskAttachment $attachment): string
    {
        return route('projects.tasks.attachments.preview', [$project, $task, $attachment]);
    }

    private function storeImageAttachment(
        Project $project,
        Task $task,
        User $user,
        string $filename,
        string $mime,
        int $sizeKb = 10,
    ): TaskAttachment {
        $this->actingAs($user)
            ->post(route('projects.tasks.attachments.store', [$project, $task]), [
                'file' => UploadedFile::fake()->create($filename, $sizeKb, $mime),
            ])
            ->assertRedirect();

        return TaskAttachment::query()->where('task_id', $task->id)->latest('id')->firstOrFail();
    }

    public function test_owner_and_member_can_preview_image_attachment(): void
    {
        ['owner' => $owner, 'member' => $member, 'project' => $project] = $this->sharedProject();
        $task = Task::factory()->for($project)->create();
        $attachment = $this->storeImageAttachment($project, $task, $owner, 'photo.png', 'image/png');

        Storage::disk(TaskAttachment::STORAGE_DISK)->put($attachment->path, 'fake-image-bytes');

        $this->actingAs($owner)
            ->get($this->previewUrl($project, $task, $attachment))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');

        $this->actingAs($member)
            ->get($this->previewUrl($project, $task, $attachment))
            ->assertOk();
    }

    public function test_jpeg_mime_preview_works_for_jfif_style_upload(): void
    {
        ['owner' => $owner, 'project' => $project, 'task' => $task] = $this->ownedTask();
        $attachment = $this->storeImageAttachment($project, $task, $owner, 'gclaass.jfif', 'image/jpeg');
        Storage::disk(TaskAttachment::STORAGE_DISK)->put($attachment->path, 'jpeg-bytes');

        $this->actingAs($owner)
            ->get($this->previewUrl($project, $task, $attachment))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');
    }

    public function test_preview_is_inline_not_download_attachment(): void
    {
        ['owner' => $owner, 'project' => $project, 'task' => $task] = $this->ownedTask();
        $attachment = $this->storeImageAttachment($project, $task, $owner, 'inline.png', 'image/png');
        Storage::disk(TaskAttachment::STORAGE_DISK)->put($attachment->path, 'png');

        $response = $this->actingAs($owner)->get($this->previewUrl($project, $task, $attachment));

        $response->assertOk();
        $this->assertStringContainsString('inline', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_outsider_and_guest_cannot_preview_image(): void
    {
        ['owner' => $owner, 'project' => $project, 'task' => $task] = $this->ownedTask();
        $attachment = $this->storeImageAttachment($project, $task, $owner, 'secret.png', 'image/png');
        Storage::disk(TaskAttachment::STORAGE_DISK)->put($attachment->path, 'png');
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->get($this->previewUrl($project, $task, $attachment))
            ->assertForbidden();

        auth()->logout();

        $this->get($this->previewUrl($project, $task, $attachment))
            ->assertRedirect(route('login'));
    }

    public function test_preview_returns_not_found_for_wrong_project_task_or_attachment(): void
    {
        $owner = User::factory()->create();
        $projectA = Project::factory()->for($owner)->create();
        $projectB = Project::factory()->for($owner)->create();
        $taskA = Task::factory()->for($projectA)->create();
        $taskB = Task::factory()->for($projectB)->create();
        $attachmentOnB = $this->storeImageAttachment($projectB, $taskB, $owner, 'b.png', 'image/png');
        Storage::disk(TaskAttachment::STORAGE_DISK)->put($attachmentOnB->path, 'png');

        $this->actingAs($owner)
            ->get($this->previewUrl($projectA, $taskB, $attachmentOnB))
            ->assertNotFound();

        $this->actingAs($owner)
            ->get($this->previewUrl($projectA, $taskA, $attachmentOnB))
            ->assertNotFound();
    }

    public function test_pdf_cannot_be_previewed_through_image_preview_route(): void
    {
        ['owner' => $owner, 'project' => $project, 'task' => $task] = $this->ownedTask();

        $this->actingAs($owner)
            ->post(route('projects.tasks.attachments.store', [$project, $task]), [
                'file' => UploadedFile::fake()->create('invoice.pdf', 20, 'application/pdf'),
            ])
            ->assertRedirect();

        $attachment = TaskAttachment::query()->where('task_id', $task->id)->firstOrFail();
        Storage::disk(TaskAttachment::STORAGE_DISK)->put($attachment->path, '%PDF');

        $this->actingAs($owner)
            ->get($this->previewUrl($project, $task, $attachment))
            ->assertNotFound();
    }

    public function test_task_show_renders_image_preview_markup_without_storage_path(): void
    {
        ['owner' => $owner, 'project' => $project, 'task' => $task] = $this->ownedTask();
        $image = $this->storeImageAttachment($project, $task, $owner, 'hover.png', 'image/png');
        Storage::disk(TaskAttachment::STORAGE_DISK)->put($image->path, 'png');

        $this->actingAs($owner)
            ->post(route('projects.tasks.attachments.store', [$project, $task]), [
                'file' => UploadedFile::fake()->create('invoice.pdf', 20, 'application/pdf'),
            ]);

        $pdf = TaskAttachment::query()->where('original_name', 'invoice.pdf')->firstOrFail();

        $html = $this->actingAs($owner)
            ->get(route('projects.tasks.show', [$project, $task]))
            ->assertOk()
            ->assertSee('data-attachment-preview-image', false)
            ->assertSee('data-attachment-preview-url', false)
            ->assertSee('data-attachment-preview-img', false)
            ->assertSee('attachment-image-modal', false)
            ->assertSee('PDF', false)
            ->assertSee('data-attachment-type-card', false)
            ->getContent();

        $this->assertStringNotContainsString($image->path, (string) $html);
        $this->assertStringNotContainsString($pdf->path, (string) $html);
        $this->assertStringContainsString(
            route('projects.tasks.attachments.preview', [$project, $task, $image]),
            (string) $html
        );
        $this->assertStringNotContainsString(
            route('projects.tasks.attachments.preview', [$project, $task, $pdf]),
            (string) $html
        );
    }

    public function test_attachment_download_delete_and_upload_still_work_after_preview_feature(): void
    {
        ['owner' => $owner, 'project' => $project, 'task' => $task] = $this->ownedTask();
        $attachment = $this->storeImageAttachment($project, $task, $owner, 'keep.png', 'image/png');
        Storage::disk(TaskAttachment::STORAGE_DISK)->put($attachment->path, 'png');

        $this->actingAs($owner)
            ->get(route('projects.tasks.attachments.download', [$project, $task, $attachment]))
            ->assertOk();

        $this->actingAs($owner)
            ->get($this->previewUrl($project, $task, $attachment))
            ->assertOk();

        $this->actingAs($owner)
            ->delete(route('projects.tasks.attachments.destroy', [$project, $task, $attachment]))
            ->assertRedirect();

        $this->assertDatabaseMissing('task_attachments', ['id' => $attachment->id]);
    }
}
