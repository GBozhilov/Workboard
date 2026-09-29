<?php

namespace App\Http\Controllers;

use App\Events\CommentCreated;
use App\Http\Requests\StoreCommentRequest;
use App\Models\Comment;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class CommentController extends Controller
{
    public function store(StoreCommentRequest $request, Project $project, Task $task): RedirectResponse
    {
        $comment = $task->comments()->create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        CommentCreated::dispatch($comment, $request->user());

        return redirect()
            ->route('projects.tasks.show', [$project, $task])
            ->with('status', 'Comment added.');
    }

    public function destroy(Project $project, Task $task, Comment $comment): RedirectResponse
    {
        Gate::authorize('delete', $comment);

        $comment->delete();

        return redirect()
            ->route('projects.tasks.show', [$project, $task])
            ->with('status', 'Comment removed.');
    }
}
