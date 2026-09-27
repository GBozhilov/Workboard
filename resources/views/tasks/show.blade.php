@extends('layouts.app')

@section('title', $task->title.' — '.$project->name)

@section('content')
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
                                onsubmit="return confirm('Delete this task?');"
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
    </div>
@endsection
