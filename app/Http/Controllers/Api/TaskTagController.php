<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTaskTagRequest;
use App\Http\Resources\TagResource;
use App\Models\Project;
use App\Models\Tag;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class TaskTagController extends Controller
{
    public function store(StoreTaskTagRequest $request, Project $project, Task $task): JsonResponse
    {
        $tag = DB::transaction(function () use ($request, $project, $task): Tag {
            if ($request->filled('tag_id')) {
                return $project->tags()->whereKey($request->integer('tag_id'))->firstOrFail();
            }

            return Tag::findOrCreateForProject($project, (string) $request->input('name'));
        });

        $task->tags()->syncWithoutDetaching([$tag->id]);

        return TagResource::make($tag)
            ->response()
            ->setStatusCode(201);
    }

    public function attach(Project $project, Task $task, Tag $tag): JsonResponse
    {
        Gate::authorize('update', $task);

        if ($tag->project_id !== $project->id) {
            abort(404);
        }

        $task->tags()->syncWithoutDetaching([$tag->id]);

        return TagResource::make($tag)
            ->response()
            ->setStatusCode(200);
    }

    public function destroy(Project $project, Task $task, Tag $tag): JsonResponse
    {
        Gate::authorize('update', $task);

        if ($tag->project_id !== $project->id) {
            abort(404);
        }

        if (! $task->tags()->whereKey($tag->id)->exists()) {
            abort(404);
        }

        $task->tags()->detach($tag->id);

        return response()->json(null, 204);
    }
}
