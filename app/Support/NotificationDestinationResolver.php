<?php

namespace App\Support;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Gate;

class NotificationDestinationResolver
{
    public function resolve(User $user, DatabaseNotification $notification): RedirectResponse
    {
        $data = $notification->data;
        $type = $data['type'] ?? null;

        return match ($type) {
            'project_member_added' => $this->resolveProjectOnly($user, $data),
            'task_created', 'comment_created', 'attachment_uploaded' => $this->resolveTaskRelated($user, $data, $type),
            default => $this->itemUnavailable(),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveProjectOnly(User $user, array $data): RedirectResponse
    {
        $projectId = $data['project_id'] ?? null;

        if ($projectId === null) {
            return $this->projectUnavailable();
        }

        $project = Project::query()->find($projectId);

        if ($project !== null && Gate::forUser($user)->allows('view', $project)) {
            return redirect()->route('projects.show', $project);
        }

        return $this->projectUnavailable();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveTaskRelated(User $user, array $data, string $type): RedirectResponse
    {
        $projectId = $data['project_id'] ?? null;
        $taskId = $data['task_id'] ?? null;

        if ($projectId === null) {
            return $this->itemUnavailable();
        }

        $project = Project::query()->find($projectId);

        if ($project === null || ! Gate::forUser($user)->allows('view', $project)) {
            return $this->itemUnavailable();
        }

        if ($taskId === null) {
            return redirect()
                ->route('projects.show', $project)
                ->with('status', 'This task no longer exists.');
        }

        $task = Task::query()
            ->whereKey($taskId)
            ->where('project_id', $project->id)
            ->first();

        if ($task === null || ! Gate::forUser($user)->allows('view', $task)) {
            return redirect()
                ->route('projects.show', $project)
                ->with('status', 'This task no longer exists.');
        }

        $url = route('projects.tasks.show', [$project, $task]);

        $fragment = match ($type) {
            'comment_created' => '#comments',
            'attachment_uploaded' => '#attachments',
            default => '',
        };

        return redirect()->to($url.$fragment);
    }

    private function itemUnavailable(): RedirectResponse
    {
        return redirect()
            ->route('notifications.index')
            ->with('status', 'The referenced item is no longer available.');
    }

    private function projectUnavailable(): RedirectResponse
    {
        return redirect()
            ->route('notifications.index')
            ->with('status', 'The referenced project is no longer available.');
    }
}
