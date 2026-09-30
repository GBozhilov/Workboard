@extends('layouts.app')

@section('title', 'Dashboard — '.config('app.name'))

@section('content')
    <div class="mx-auto max-w-6xl px-4 py-12 sm:px-6 lg:px-8">
        <div class="rounded-2xl border border-slate-200/80 bg-white p-8 shadow-sm sm:p-10">
            <p class="text-sm font-semibold uppercase tracking-wider text-indigo-600">Dashboard</p>
            <h1 class="mt-3 text-3xl font-semibold tracking-tight text-slate-900">
                Hello, {{ auth()->user()->name }}
            </h1>
            <p class="mt-4 max-w-2xl text-base leading-relaxed text-slate-600">
                You are signed in to WorkBoard. Open your projects to manage tasks, members, tags, and notifications.
            </p>
            <div class="mt-8">
                <a
                    href="{{ route('projects.index') }}"
                    class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500"
                >
                    View projects
                </a>
            </div>
        </div>
    </div>
@endsection
