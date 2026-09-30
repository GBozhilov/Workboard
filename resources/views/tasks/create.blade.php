@extends('layouts.app')

@section('title', 'New task — '.$project->name)

@section('content')
    <div class="mx-auto max-w-2xl px-4 py-12 sm:px-6 lg:px-8">
        <a href="{{ route('projects.show', $project) }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">&larr; Back to project</a>

        <div class="mt-6 rounded-2xl border border-slate-200/80 bg-white p-8 shadow-sm">
            <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Create task</h1>
            <p class="mt-1 text-sm text-slate-600">{{ $project->name }}</p>

            <form method="POST" action="{{ route('projects.tasks.store', $project) }}" class="mt-8 space-y-6">
                @csrf
                @include('tasks._form', ['project' => $project, 'task' => null])

                <x-primary-button class="w-full sm:w-auto">
                    Save task
                </x-primary-button>
            </form>
        </div>
    </div>
@endsection
