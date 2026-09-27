@extends('layouts.app')

@section('title', 'Projects — '.config('app.name'))

@section('content')
    <div class="mx-auto max-w-6xl px-4 py-12 sm:px-6 lg:px-8">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wider text-indigo-600">Projects</p>
                <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-900">Your projects</h1>
            </div>
            <a
                href="{{ route('projects.create') }}"
                class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500"
            >
                New project
            </a>
        </div>

        <x-auth-session-status class="mt-6" :status="session('status')" />

        @if ($projects->isEmpty())
            <div class="mt-10 rounded-2xl border border-dashed border-slate-300 bg-white/80 p-10 text-center shadow-sm">
                <h2 class="text-lg font-semibold text-slate-900">No projects yet</h2>
                <p class="mt-2 text-sm text-slate-600">Create your first project to start organizing work in WorkBoard.</p>
                <a
                    href="{{ route('projects.create') }}"
                    class="mt-6 inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500"
                >
                    Create project
                </a>
            </div>
        @else
            <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($projects as $project)
                    <a
                        href="{{ route('projects.show', $project) }}"
                        class="group block cursor-pointer rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm transition hover:border-indigo-200 hover:bg-slate-50/80 hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2"
                    >
                        <h2 class="text-lg font-semibold text-slate-900 transition group-hover:text-indigo-600">
                            {{ $project->name }}
                        </h2>
                        @if ($project->description)
                            <p class="mt-2 line-clamp-3 text-sm leading-relaxed text-slate-600">
                                {{ $project->description }}
                            </p>
                        @endif
                        <p class="mt-4 text-xs text-slate-400">Updated {{ $project->updated_at->diffForHumans() }}</p>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
@endsection
