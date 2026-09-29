<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTaskTagRequest;
use App\Models\Project;
use App\Models\Tag;
use App\Models\Task;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class TaskTagController extends Controller
{
    public function store(StoreTaskTagRequest $request, Project $project, Task $task): RedirectResponse
    {
        DB::transaction(function () use ($request, $project, $task): void {
            if ($request->filled('tag_id')) {
                $tag = $project->tags()->whereKey($request->integer('tag_id'))->firstOrFail();
            } else {
                $tag = Tag::findOrCreateForProject($project, (string) $request->input('name'));
            }

            $task->tags()->syncWithoutDetaching([$tag->id]);
        });

        return redirect()
            ->route('projects.tasks.show', [$project, $task])
            ->with('status', 'Tag added.');
    }

    public function destroy(Project $project, Task $task, Tag $tag): RedirectResponse
    {
        Gate::authorize('update', $task);

        if ($tag->project_id !== $project->id) {
            abort(404);
        }

        if (! $task->tags()->whereKey($tag->id)->exists()) {
            abort(404);
        }

        $task->tags()->detach($tag->id);

        return redirect()
            ->route('projects.tasks.show', [$project, $task])
            ->with('status', 'Tag removed.');
    }
}
