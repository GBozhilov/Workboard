@extends('layouts.app')

@section('title', 'Log in — '.config('app.name'))

@section('content')
    <div class="mx-auto max-w-md px-4 py-12 sm:px-6 lg:px-8">
        <div class="rounded-2xl border border-slate-200/80 bg-white p-8 shadow-sm">
            <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Log in</h1>
            <p class="mt-2 text-sm text-slate-600">Welcome back to WorkBoard.</p>

            <x-auth-session-status class="mt-6" :status="session('status')" />

            <form method="POST" action="{{ route('login') }}" class="mt-8 space-y-6">
                @csrf

                <div>
                    <x-input-label for="email" value="Email" />
                    <x-text-input id="email" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
                    <x-input-error :messages="$errors->get('email')" />
                </div>

                <div>
                    <x-input-label for="password" value="Password" />
                    <x-text-input id="password" type="password" name="password" required autocomplete="current-password" />
                    <x-input-error :messages="$errors->get('password')" />
                </div>

                <button
                    type="submit"
                    class="inline-flex w-full items-center justify-center rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 focus-visible:ring-offset-2"
                >
                    Log in
                </button>
            </form>

            <p class="mt-6 text-center text-sm text-slate-600">
                Don't have an account?
                <a href="{{ route('register') }}" class="font-medium text-indigo-600 hover:text-indigo-500">Register</a>
            </p>
        </div>
    </div>
@endsection
