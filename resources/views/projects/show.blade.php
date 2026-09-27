@extends('layouts.app')

@section('title', $project->name.' — '.config('app.name'))

@section('content')
    <div class="mx-auto max-w-3xl px-4 py-12 sm:px-6 lg:px-8">
        <a href="{{ route('projects.index') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">&larr; Back to projects</a>

        <x-auth-session-status class="mt-6" :status="session('status')" />

        <div class="mt-6 rounded-2xl border border-slate-200/80 bg-white p-8 shadow-sm sm:p-10">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wider text-indigo-600">Project</p>
                    <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-900">{{ $project->name }}</h1>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a
                        href="{{ route('projects.edit', $project) }}"
                        class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50"
                    >
                        Edit
                    </a>
                    <form
                        method="POST"
                        action="{{ route('projects.destroy', $project) }}"
                        onsubmit="return confirm('Delete this project? This cannot be undone.');"
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
                </div>
            </div>

            @if ($project->description)
                <div class="mt-8 border-t border-slate-100 pt-8">
                    <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-500">Description</h2>
                    <p class="mt-3 whitespace-pre-wrap text-base leading-relaxed text-slate-700">{{ $project->description }}</p>
                </div>
            @else
                <p class="mt-8 text-sm text-slate-500">No description provided.</p>
            @endif
        </div>

        <div class="mt-8 rounded-2xl border border-slate-200/80 bg-white p-8 shadow-sm">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">Tasks</h2>
                    <p class="mt-1 text-sm text-slate-500">Work items for this project.</p>
                </div>
                <a
                    href="{{ route('projects.tasks.create', $project) }}"
                    class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500"
                >
                    Create task
                </a>
            </div>

            @if ($project->tasks->isEmpty())
                <p class="mt-8 rounded-xl border border-dashed border-slate-300 bg-slate-50/80 px-6 py-8 text-center text-sm text-slate-600">
                    No tasks yet. Create the first task for this project.
                </p>
            @else
                <ul class="mt-6 divide-y divide-slate-100">
                    @foreach ($project->tasks as $task)
                        <li class="flex flex-col gap-3 py-4 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <a
                                    href="{{ route('projects.tasks.show', [$project, $task]) }}"
                                    class="font-medium text-slate-900 hover:text-indigo-600"
                                >
                                    {{ $task->title }}
                                </a>
                                @if ($task->due_date)
                                    <p class="mt-1 text-xs text-slate-500">Due {{ $task->due_date->format('M j, Y') }}</p>
                                @endif
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <x-task-status-badge :status="$task->status" />
                                <x-task-priority-badge :priority="$task->priority" />
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
@endsection
