<?php

namespace App\Http\Controllers;

use App\Events\TaskCreated;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class TaskController extends Controller
{
    public function index(Project $project): RedirectResponse
    {
        Gate::authorize('view', $project);

        return redirect()->route('projects.show', $project);
    }

    public function create(Project $project): View
    {
        Gate::authorize('create', [Task::class, $project]);

        return view('tasks.create', compact('project'));
    }

    public function store(StoreTaskRequest $request, Project $project): RedirectResponse
    {
        $task = $project->tasks()->create($request->validated());

        TaskCreated::dispatch($task, $request->user());

        return redirect()
            ->route('projects.tasks.show', [$project, $task])
            ->with('status', 'Task created successfully.');
    }

    public function show(Project $project, Task $task): View
    {
        Gate::authorize('view', $task);

        $task->load(['comments.user', 'tags', 'attachments.user', 'assignee']);

        $projectTags = $project->tags()->get();

        $availableTags = $project->tags()
            ->whereNotIn('tags.id', $task->tags()->select('tags.id'))
            ->get();

        return view('tasks.show', compact('project', 'task', 'projectTags', 'availableTags'));
    }

    public function edit(Project $project, Task $task): View
    {
        Gate::authorize('update', $task);

        return view('tasks.edit', compact('project', 'task'));
    }

    public function update(UpdateTaskRequest $request, Project $project, Task $task): RedirectResponse
    {
        $task->update($request->validated());

        return redirect()
            ->route('projects.tasks.show', [$project, $task])
            ->with('status', 'Task updated successfully.');
    }

    public function destroy(Project $project, Task $task): RedirectResponse
    {
        Gate::authorize('delete', $task);

        $task->delete();

        return redirect()
            ->route('projects.show', $project)
            ->with('status', 'Task deleted successfully.');
    }
}
