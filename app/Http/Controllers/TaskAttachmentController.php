<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTaskAttachmentRequest;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskAttachment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TaskAttachmentController extends Controller
{
    public function store(StoreTaskAttachmentRequest $request, Project $project, Task $task): RedirectResponse
    {
        $uploaded = $request->file('file');
        $directory = sprintf('task-attachments/%d/%d', $project->id, $task->id);
        $extension = $uploaded->guessExtension();
        $storedFilename = Str::uuid()->toString().($extension ? '.'.$extension : '');
        $path = $uploaded->storeAs($directory, $storedFilename, TaskAttachment::STORAGE_DISK);

        if ($path === false) {
            return redirect()
                ->route('projects.tasks.show', [$project, $task])
                ->withErrors(['file' => 'The file could not be stored.']);
        }

        try {
            $task->attachments()->create([
                'user_id' => $request->user()->id,
                'original_name' => basename($uploaded->getClientOriginalName()),
                'path' => $path,
                'mime_type' => (string) ($uploaded->getMimeType() ?? 'application/octet-stream'),
                'size' => (int) $uploaded->getSize(),
            ]);
        } catch (\Throwable $exception) {
            Storage::disk(TaskAttachment::STORAGE_DISK)->delete($path);

            throw $exception;
        }

        return redirect()
            ->route('projects.tasks.show', [$project, $task])
            ->with('status', 'File attached.');
    }

    public function preview(Project $project, Task $task, TaskAttachment $attachment): Response
    {
        Gate::authorize('preview', $attachment);

        if ($attachment->task_id !== $task->id || $task->project_id !== $project->id) {
            abort(404);
        }

        if (! $attachment->isPreviewableImage()) {
            abort(404);
        }

        $disk = Storage::disk(TaskAttachment::STORAGE_DISK);

        if (! $disk->exists($attachment->path)) {
            abort(404);
        }

        return $disk->response($attachment->path, null, [
            'Content-Type' => $attachment->mime_type,
            'Content-Disposition' => 'inline',
        ]);
    }

    public function download(Project $project, Task $task, TaskAttachment $attachment): StreamedResponse
    {
        Gate::authorize('download', $attachment);

        if ($attachment->task_id !== $task->id || $task->project_id !== $project->id) {
            abort(404);
        }

        $disk = Storage::disk(TaskAttachment::STORAGE_DISK);

        if (! $disk->exists($attachment->path)) {
            abort(404);
        }

        return $disk->download($attachment->path, $attachment->original_name);
    }

    public function destroy(Project $project, Task $task, TaskAttachment $attachment): RedirectResponse
    {
        Gate::authorize('delete', $attachment);

        if ($attachment->task_id !== $task->id || $task->project_id !== $project->id) {
            abort(404);
        }

        $attachment->delete();

        return redirect()
            ->route('projects.tasks.show', [$project, $task])
            ->with('status', 'Attachment removed.');
    }
}
