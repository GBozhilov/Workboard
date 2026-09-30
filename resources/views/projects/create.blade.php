@extends('layouts.app')

@section('title', 'New project — '.config('app.name'))

@section('content')
    <div class="mx-auto max-w-2xl px-4 py-12 sm:px-6 lg:px-8">
        <a href="{{ route('projects.index') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">&larr; Back to projects</a>

        <div class="mt-6 rounded-2xl border border-slate-200/80 bg-white p-8 shadow-sm">
            <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Create project</h1>

            <form method="POST" action="{{ route('projects.store') }}" class="mt-8 space-y-6">
                @csrf
                @include('projects._form')

                <x-primary-button class="w-full sm:w-auto">
                    Save project
                </x-primary-button>
            </form>
        </div>
    </div>
@endsection
