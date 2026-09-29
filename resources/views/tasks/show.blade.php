@extends('layouts.app')

@section('title', $task->title.' — '.$project->name)

@section('content')
    @php
        use App\Models\Comment;
        use App\Models\TaskAttachment;
    @endphp
    <div class="mx-auto max-w-3xl px-4 py-12 sm:px-6 lg:px-8">
        <a href="{{ route('projects.show', $project) }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">&larr; Back to project</a>

        <x-auth-session-status class="mt-6" :status="session('status')" />

        <div class="mt-6 rounded-2xl border border-slate-200/80 bg-white p-8 shadow-sm sm:p-10">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wider text-indigo-600">Task</p>
                    <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-900">{{ $task->title }}</h1>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <x-task-status-badge :status="$task->status" />
                        <x-task-priority-badge :priority="$task->priority" />
                    </div>
                </div>
                @canany(['update', 'delete'], $task)
                    <div class="flex flex-wrap gap-2">
                        @can('update', $task)
                            <a
                                href="{{ route('projects.tasks.edit', [$project, $task]) }}"
                                class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50"
                            >
                                Edit
                            </a>
                        @endcan
                        @can('delete', $task)
                            <form
                                method="POST"
                                action="{{ route('projects.tasks.destroy', [$project, $task]) }}"
                                data-confirm="true"
                                data-confirm-title="Delete task"
                                data-confirm-message="Delete this task?"
                                data-confirm-label="Delete task"
                                data-confirm-destructive
                            >
                                @csrf
                                @method('DELETE')
                                <button
                                    type="submit"
                                    class="inline-flex items-center justify-center rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-sm font-semibold text-red-700 transition hover:bg-red-100"
                                >
                                    Delete
                                </button>
                            </form>
                        @endcan
                    </div>
                @endcanany
            </div>

            @if ($task->due_date)
                <p class="mt-6 text-sm text-slate-600">
                    <span class="font-medium text-slate-700">Due:</span>
                    {{ $task->due_date->format('M j, Y') }}
                </p>
            @endif

            @if ($task->description)
                <div class="mt-8 border-t border-slate-100 pt-8">
                    <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-500">Description</h2>
                    <p class="mt-3 whitespace-pre-wrap text-base leading-relaxed text-slate-700">{{ $task->description }}</p>
                </div>
            @else
                <p class="mt-8 text-sm text-slate-500">No description provided.</p>
            @endif
        </div>

        <section class="mt-8 rounded-2xl border border-slate-200/80 bg-white p-8 shadow-sm sm:p-10" aria-label="Tags">
            <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-500">Tags</h2>

            @if ($task->tags->isNotEmpty())
                <ul class="mt-4 flex flex-wrap gap-2">
                    @foreach ($task->tags as $tag)
                        <li class="inline-flex items-center gap-1">
                            <x-task-tag-badge :tag="$tag" />
                            @can('update', $task)
                                <form
                                    method="POST"
                                    action="{{ route('projects.tasks.tags.destroy', [$project, $task, $tag]) }}"
                                    class="inline"
                                    data-confirm="true"
                                    data-confirm-title="Remove tag"
                                    data-confirm-message="Remove this tag from the task?"
                                    data-confirm-label="Remove tag"
                                    data-confirm-destructive
                                >
                                    @csrf
                                    @method('DELETE')
                                    <button
                                        type="submit"
                                        class="rounded p-0.5 text-slate-400 transition hover:text-red-600"
                                        aria-label="Remove tag {{ $tag->name }}"
                                    >
                                        &times;
                                    </button>
                                </form>
                            @endcan
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="mt-4 text-sm text-slate-500">No tags yet.</p>
            @endif

            @can('update', $task)
                <form method="POST" action="{{ route('projects.tasks.tags.store', [$project, $task]) }}" class="mt-6 border-t border-slate-100 pt-6">
                    @csrf
                    <label for="tag-name" class="block text-sm font-medium text-slate-700">Add new tag</label>
                    <p class="mt-1 text-sm text-slate-500">Create a new project tag or type a name already used in this project to reuse it.</p>
                    <div class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-end">
                        <div class="flex-1">
                            <input
                                id="tag-name"
                                name="name"
                                type="text"
                                list="project-tag-suggestions"
                                maxlength="50"
                                class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                value="{{ old('name') }}"
                                placeholder="e.g. urgent"
                            />
                            <datalist id="project-tag-suggestions">
                                @foreach ($projectTags as $suggestedTag)
                                    <option value="{{ $suggestedTag->name }}"></option>
                                @endforeach
                            </datalist>
                            @error('name')
                                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <button
                            type="submit"
                            class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500"
                        >
                            Add new tag
                        </button>
                    </div>
                </form>

                <form method="POST" action="{{ route('projects.tasks.tags.store', [$project, $task]) }}" class="mt-4">
                    @csrf
                    <label for="tag-id" class="block text-sm font-medium text-slate-700">Attach existing tag</label>
                    <p class="mt-1 text-sm text-slate-500">Project tags not yet on this task.</p>
                    <div class="mt-2 flex flex-col gap-3 sm:flex-row sm:items-end">
                        <select
                            id="tag-id"
                            name="tag_id"
                            class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:max-w-xs disabled:bg-slate-100 disabled:text-slate-500"
                            @disabled($availableTags->isEmpty())
                        >
                            @if ($availableTags->isEmpty())
                                <option value="">No tags available to attach</option>
                            @else
                                <option value="">Select a tag…</option>
                                @foreach ($availableTags as $availableTag)
                                    <option value="{{ $availableTag->id }}" @selected(old('tag_id') == $availableTag->id)>
                                        {{ $availableTag->name }}
                                    </option>
                                @endforeach
                            @endif
                        </select>
                        <button
                            type="submit"
                            class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
                            @disabled($availableTags->isEmpty())
                        >
                            Attach
                        </button>
                    </div>
                    @error('tag_id')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </form>
            @endcan
        </section>

        <section id="attachments" class="mt-8 rounded-2xl border border-slate-200/80 bg-white p-8 shadow-sm sm:p-10" aria-label="Attachments">
            <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-500">Attachments</h2>

            @can('create', [TaskAttachment::class, $task])
                <form
                    method="POST"
                    action="{{ route('projects.tasks.attachments.store', [$project, $task]) }}"
                    enctype="multipart/form-data"
                    class="mt-6 border-b border-slate-100 pb-6"
                >
                    @csrf
                    <label for="attachment-file" class="block text-sm font-medium text-slate-700">Attach a file</label>
                    <p class="mt-1 text-sm text-slate-500">Images, PDF, text, or common Office documents up to 10 MB.</p>
                    <div class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-end">
                        <input
                            id="attachment-file"
                            name="file"
                            type="file"
                            required
                            class="block w-full text-sm text-slate-700 file:mr-4 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100"
                        />
                        <button
                            type="submit"
                            class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500"
                        >
                            Upload
                        </button>
                    </div>
                    @error('file')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </form>
            @endcan

            @if ($task->attachments->isEmpty())
                <p class="mt-6 text-sm text-slate-500">No attachments yet.</p>
            @else
                <ul class="mt-6 space-y-4">
                    @foreach ($task->attachments as $attachment)
                        <x-task-attachment-row :project="$project" :task="$task" :attachment="$attachment" />
                    @endforeach
                </ul>
            @endif
        </section>

        <x-attachment-image-modal />

        <section id="comments" class="mt-8 rounded-2xl border border-slate-200/80 bg-white p-8 shadow-sm sm:p-10" aria-label="Comments">
            <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-500">Comments</h2>

            @can('create', [Comment::class, $task])
                <form method="POST" action="{{ route('projects.tasks.comments.store', [$project, $task]) }}" class="mt-6">
                    @csrf
                    <label for="comment-body" class="block text-sm font-medium text-slate-700">Add a comment</label>
                    <textarea
                        id="comment-body"
                        name="body"
                        rows="3"
                        required
                        class="mt-2 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >{{ old('body') }}</textarea>
                    @error('body')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    <button
                        type="submit"
                        class="mt-3 inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500"
                    >
                        Post comment
                    </button>
                </form>
            @endcan

            @if ($task->comments->isEmpty())
                <p class="mt-6 text-sm text-slate-500">No comments yet.</p>
            @else
                <ul class="mt-6 space-y-6">
                    @foreach ($task->comments as $comment)
                        <li class="border-t border-slate-100 pt-6 first:border-t-0 first:pt-0">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-semibold text-slate-900">{{ $comment->user->name }}</p>
                                    <p
                                        class="mt-0.5 text-xs text-slate-500"
                                        title="{{ $comment->created_at->format('M j, Y g:i:s A') }}"
                                    >
                                        {{ $comment->created_at->format('M j, Y') }} · {{ $comment->created_at->format('g:i A') }}
                                    </p>
                                    <p class="mt-1 whitespace-pre-wrap text-sm leading-relaxed text-slate-700">{{ $comment->body }}</p>
                                </div>
                                @can('delete', $comment)
                                    <form
                                        method="POST"
                                        action="{{ route('projects.tasks.comments.destroy', [$project, $task, $comment]) }}"
                                        class="shrink-0"
                                        data-confirm="true"
                                        data-confirm-title="Delete comment"
                                        data-confirm-message="Delete this comment?"
                                        data-confirm-label="Delete comment"
                                        data-confirm-destructive
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button
                                            type="submit"
                                            class="text-sm font-semibold text-red-600 hover:text-red-500"
                                        >
                                            Delete
                                        </button>
                                    </form>
                                @endcan
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
@endsection
