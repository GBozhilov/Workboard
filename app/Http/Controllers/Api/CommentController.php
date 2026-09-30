<?php

namespace App\Http\Controllers\Api;

use App\Events\CommentCreated;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCommentRequest;
use App\Http\Resources\CommentResource;
use App\Models\Comment;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class CommentController extends Controller
{
    public function index(Project $project, Task $task): AnonymousResourceCollection
    {
        Gate::authorize('view', $task);

        $comments = $task->comments()
            ->with('user')
            ->orderBy('created_at')
            ->paginate(15);

        return CommentResource::collection($comments);
    }

    public function store(StoreCommentRequest $request, Project $project, Task $task): JsonResponse
    {
        $comment = $task->comments()->create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        CommentCreated::dispatch($comment, $request->user());

        $comment->load('user');

        return CommentResource::make($comment)
            ->response()
            ->setStatusCode(201);
    }

    public function destroy(Project $project, Task $task, Comment $comment): JsonResponse
    {
        Gate::authorize('delete', $comment);

        $comment->delete();

        return response()->json(null, 204);
    }
}
