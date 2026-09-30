@extends('layouts.app')

@section('title', 'Edit '.$task->title)

@section('content')
    <div class="mx-auto max-w-2xl px-4 py-12 sm:px-6 lg:px-8">
        <a href="{{ route('projects.tasks.show', [$project, $task]) }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">&larr; Back to task</a>

        <div class="mt-6 rounded-2xl border border-slate-200/80 bg-white p-8 shadow-sm">
            <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Edit task</h1>

            <form method="POST" action="{{ route('projects.tasks.update', [$project, $task]) }}" class="mt-8 space-y-6">
                @csrf
                @method('PUT')
                @include('tasks._form', ['project' => $project, 'task' => $task])

                <x-primary-button class="w-full sm:w-auto">
                    Update task
                </x-primary-button>
            </form>
        </div>
    </div>
@endsection
