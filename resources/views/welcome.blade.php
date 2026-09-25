@extends('layouts.app')

@section('title', config('app.name'))

@section('content')
    <div class="mx-auto max-w-6xl px-4 pb-20 pt-12 sm:px-6 sm:pt-16 lg:px-8 lg:pb-28">
        <section class="mx-auto max-w-3xl text-center lg:text-left">
            <p class="text-sm font-semibold uppercase tracking-wider text-indigo-600">
                Project management
            </p>
            <h1 class="mt-4 text-4xl font-semibold tracking-tight text-slate-900 sm:text-5xl lg:text-[3.25rem] lg:leading-tight">
                Plan work, track progress, ship with clarity
            </h1>
            <p class="mx-auto mt-6 max-w-2xl text-lg leading-relaxed text-slate-600 lg:mx-0">
                WorkBoard is your workspace for organizing projects, tasks, and team collaboration.
                You are on the foundation release — core features will land in upcoming learning stages.
            </p>
            {{-- Hero actions: projects link uses named routes; tasks stay disabled until Stage 4. --}}
            <div class="mt-10 flex flex-col items-center justify-center gap-3 sm:flex-row lg:justify-start">
                @auth
                    {{-- Authenticated users go straight to their project list. --}}
                    <a
                        href="{{ route('projects.index') }}"
                        class="inline-flex w-full items-center justify-center rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 focus-visible:ring-offset-2 sm:w-auto"
                    >
                        View Projects
                    </a>
                @else
                    {{-- Guests must sign in before accessing projects. --}}
                    <a
                        href="{{ route('login') }}"
                        class="inline-flex w-full items-center justify-center rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 focus-visible:ring-offset-2 sm:w-auto"
                    >
                        View Projects
                    </a>
                @endauth
                {{-- Placeholder only; task CRUD is not implemented yet. --}}
                <button
                    type="button"
                    disabled
                    class="inline-flex w-full cursor-not-allowed items-center justify-center rounded-lg border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-400 opacity-60 shadow-sm sm:w-auto"
                    title="Tasks are coming in a later stage"
                >
                    Create Task
                </button>
            </div>
        </section>

        <section class="mt-16 sm:mt-20">
            <div class="mb-8 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 class="text-xl font-semibold tracking-tight text-slate-900">Workspace overview</h2>
                    <p class="mt-1 text-sm text-slate-500">Preview of what you will build next in WorkBoard.</p>
                </div>
            </div>

            <div class="grid gap-6 md:grid-cols-3">
                <article class="group rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-md sm:p-7">
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 ring-1 ring-indigo-100 transition group-hover:bg-indigo-100">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                        </svg>
                    </div>
                    <h3 class="mt-5 text-lg font-semibold text-slate-900">Projects</h3>
                    <p class="mt-2 text-sm leading-relaxed text-slate-600">
                        Group related work into projects with clear ownership and timelines.
                    </p>
                    <p class="mt-5 text-xs font-medium uppercase tracking-wide text-slate-400">Coming soon</p>
                </article>

                <article class="group rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-md sm:p-7">
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-violet-50 text-violet-600 ring-1 ring-violet-100 transition group-hover:bg-violet-100">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                        </svg>
                    </div>
                    <h3 class="mt-5 text-lg font-semibold text-slate-900">Tasks</h3>
                    <p class="mt-2 text-sm leading-relaxed text-slate-600">
                        Track status, priority, and assignments on a focused task board.
                    </p>
                    <p class="mt-5 text-xs font-medium uppercase tracking-wide text-slate-400">Coming soon</p>
                </article>

                <article class="group rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-md sm:p-7">
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-sky-50 text-sky-600 ring-1 ring-sky-100 transition group-hover:bg-sky-100">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                    </div>
                    <h3 class="mt-5 text-lg font-semibold text-slate-900">Team</h3>
                    <p class="mt-2 text-sm leading-relaxed text-slate-600">
                        Invite collaborators, assign roles, and keep everyone aligned.
                    </p>
                    <p class="mt-5 text-xs font-medium uppercase tracking-wide text-slate-400">Coming soon</p>
                </article>
            </div>
        </section>
    </div>
@endsection
