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
                You are signed in to WorkBoard. Project and task features will be added in upcoming stages.
            </p>
        </div>
    </div>
@endsection
