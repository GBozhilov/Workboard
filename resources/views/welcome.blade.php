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
                WorkBoard helps you organize projects, tasks, collaboration, and progress in one place.
            </p>
            <div class="mt-10 flex flex-col items-center justify-center gap-3 sm:flex-row lg:justify-start">
                @auth
                    <a
                        href="{{ route('projects.index') }}"
                        class="inline-flex w-full items-center justify-center rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 focus-visible:ring-offset-2 sm:w-auto"
                    >
                        View Projects
                    </a>
                    <a
                        href="{{ route('projects.create') }}"
                        class="inline-flex w-full items-center justify-center rounded-lg border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-800 shadow-sm transition hover:border-slate-400 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 focus-visible:ring-offset-2 sm:w-auto"
                    >
                        Create Project
                    </a>
                @else
                    <a
                        href="{{ route('login') }}"
                        class="inline-flex w-full items-center justify-center rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 focus-visible:ring-offset-2 sm:w-auto"
                    >
                        Log in
                    </a>
                    <a
                        href="{{ route('register') }}"
                        class="inline-flex w-full items-center justify-center rounded-lg border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-800 shadow-sm transition hover:border-slate-400 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 focus-visible:ring-offset-2 sm:w-auto"
                    >
                        Register
                    </a>
                @endauth
            </div>
        </section>

        @auth
            <section class="mt-16 sm:mt-20">
                <div class="mb-8">
                    <h2 class="text-xl font-semibold tracking-tight text-slate-900">Your workspace</h2>
                    <p class="mt-1 text-sm text-slate-500">A quick snapshot of projects and work you can access.</p>
                </div>

                <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    <a
                        href="{{ route('projects.index') }}"
                        class="group rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:border-indigo-200 hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 focus-visible:ring-offset-2"
                    >
                        <p class="text-3xl font-semibold tracking-tight text-slate-900" data-testid="overview-projects-count">{{ $overview->accessibleProjectsCount }}</p>
                        <h3 class="mt-2 text-sm font-semibold text-slate-900">Projects</h3>
                        <p class="mt-1 text-sm text-slate-600">Owned and shared workspaces you can open.</p>
                    </a>

                    <a
                        href="{{ route('projects.index') }}"
                        class="group rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:border-indigo-200 hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 focus-visible:ring-offset-2"
                    >
                        <p class="text-3xl font-semibold tracking-tight text-slate-900" data-testid="overview-open-tasks-count">{{ $overview->openTasksCount }}</p>
                        <h3 class="mt-2 text-sm font-semibold text-slate-900">Open tasks</h3>
                        <p class="mt-1 text-sm text-slate-600">To do and in progress across your projects.</p>
                    </a>

                    <a
                        href="{{ route('notifications.index') }}"
                        class="group rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:border-indigo-200 hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 focus-visible:ring-offset-2"
                    >
                        <p class="text-3xl font-semibold tracking-tight text-slate-900" data-testid="overview-unread-notifications-count">{{ $overview->unreadNotificationsCount }}</p>
                        <h3 class="mt-2 text-sm font-semibold text-slate-900">Unread notifications</h3>
                        <p class="mt-1 text-sm text-slate-600">Updates waiting in your inbox.</p>
                    </a>

                    <div class="rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm">
                        <p class="text-3xl font-semibold tracking-tight text-slate-900" data-testid="overview-assigned-count">{{ $overview->assignedToMeOpenTasksCount }}</p>
                        <h3 class="mt-2 text-sm font-semibold text-slate-900">Assigned to me</h3>
                        <p class="mt-1 text-sm text-slate-600">Open tasks on your plate. Browse projects to open them.</p>
                    </div>
                </div>
            </section>
        @endauth
    </div>
@endsection
