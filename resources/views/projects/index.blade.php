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
                class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 focus-visible:ring-offset-2"
            >
                Create project
            </a>
        </div>

        <x-auth-session-status class="mt-6" :status="session('status')" />

        <form method="GET" action="{{ route('projects.index') }}" class="mt-8 rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm sm:p-6">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="sm:col-span-2">
                    <x-input-label for="search" value="Search" />
                    <x-text-input
                        id="search"
                        name="search"
                        type="search"
                        class="mt-1 block w-full"
                        :value="$search"
                        placeholder="Project name or description"
                    />
                </div>
                <div>
                    <x-input-label for="sort" value="Sort" />
                    <select
                        id="sort"
                        name="sort"
                        class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                    >
                        <option value="newest" @selected($sort === 'newest')>Newest</option>
                        <option value="oldest" @selected($sort === 'oldest')>Oldest</option>
                        <option value="name_asc" @selected($sort === 'name_asc')>Name A–Z</option>
                        <option value="name_desc" @selected($sort === 'name_desc')>Name Z–A</option>
                    </select>
                </div>
                <div class="flex items-end gap-2">
                    <button
                        type="submit"
                        class="inline-flex flex-1 items-center justify-center rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500"
                    >
                        Apply
                    </button>
                    @if ($hasActiveFilters)
                        <a
                            href="{{ route('projects.index') }}"
                            class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50"
                        >
                            Reset
                        </a>
                    @endif
                </div>
            </div>
        </form>

        @if ($projects->total() === 0 && ! $hasActiveFilters)
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
        @elseif ($projects->isEmpty())
            <div class="mt-10 rounded-2xl border border-dashed border-slate-300 bg-slate-50/80 p-10 text-center shadow-sm">
                <h2 class="text-lg font-semibold text-slate-900">No matching projects</h2>
                <p class="mt-2 text-sm text-slate-600">Try a different search term or reset your filters.</p>
                <a
                    href="{{ route('projects.index') }}"
                    class="mt-6 inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50"
                >
                    Reset filters
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

            <x-listing-pagination :paginator="$projects" />
        @endif
    </div>
@endsection
