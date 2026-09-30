<?php

namespace App\Http\Controllers\Api;

use App\Events\TaskCreated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\IndexApiTaskRequest;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class TaskController extends Controller
{
    public function index(IndexApiTaskRequest $request, Project $project): AnonymousResourceCollection
    {
        $tasks = $project->tasks()
            ->with(['tags', 'assignee'])
            ->withCount(['comments', 'attachments'])
            ->search($request->searchTerm())
            ->filterStatus($request->statusFilter())
            ->filterPriority($request->priorityFilter())
            ->filterTag($request->tagFilter())
            ->sorted($request->sort())
            ->paginate($request->perPage());

        return TaskResource::collection($tasks);
    }

    public function store(StoreTaskRequest $request, Project $project): JsonResponse
    {
        $task = $project->tasks()->create($request->validated());

        TaskCreated::dispatch($task, $request->user());

        $task->load(['tags', 'assignee'])->loadCount(['comments', 'attachments']);

        return TaskResource::make($task)
            ->response()
            ->setStatusCode(201);
    }

    public function show(Project $project, Task $task): TaskResource
    {
        Gate::authorize('view', $task);

        $task->load(['tags', 'assignee'])->loadCount(['comments', 'attachments']);

        return TaskResource::make($task);
    }

    public function update(UpdateTaskRequest $request, Project $project, Task $task): TaskResource
    {
        $task->update($request->validated());
        $task->load(['tags', 'assignee'])->loadCount(['comments', 'attachments']);

        return TaskResource::make($task);
    }

    public function destroy(Project $project, Task $task): JsonResponse
    {
        Gate::authorize('delete', $task);

        $task->delete();

        return response()->json(null, 204);
    }
}
