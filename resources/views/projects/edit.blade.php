@extends('layouts.app')

@section('title', 'Edit '.$project->name.' — '.config('app.name'))

@section('content')
    <div class="mx-auto max-w-2xl px-4 py-12 sm:px-6 lg:px-8">
        <a href="{{ route('projects.show', $project) }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">&larr; Back to project</a>

        <div class="mt-6 rounded-2xl border border-slate-200/80 bg-white p-8 shadow-sm">
            <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Edit project</h1>

            <form method="POST" action="{{ route('projects.update', $project) }}" class="mt-8 space-y-6">
                @csrf
                @method('PUT')
                @include('projects._form', ['project' => $project])

                <button
                    type="submit"
                    class="inline-flex w-full items-center justify-center rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500 sm:w-auto"
                >
                    Update project
                </button>
            </form>
        </div>
    </div>
@endsection
