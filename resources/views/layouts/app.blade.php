<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>@yield('title', config('app.name'))</title>
        @fonts
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="min-h-screen bg-slate-50 font-sans text-slate-900 antialiased">
        <div class="pointer-events-none fixed inset-0 -z-10 bg-[radial-gradient(ellipse_80%_50%_at_50%_-20%,rgba(99,102,241,0.12),transparent)]"></div>

        <header class="sticky top-0 z-50 border-b border-slate-200/80 bg-white/90 shadow-sm backdrop-blur-md">
            <div class="mx-auto flex h-16 max-w-6xl items-center justify-between gap-6 px-4 sm:px-6 lg:px-8">
                <a href="{{ url('/') }}" class="flex items-center gap-2.5 text-slate-900 transition-colors hover:text-indigo-600">
                    <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-indigo-600 text-white shadow-sm">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2" />
                        </svg>
                    </span>
                    <span class="text-lg font-semibold tracking-tight">{{ config('app.name') }}</span>
                </a>

                <nav class="flex flex-wrap items-center justify-end gap-1 sm:gap-2">
                    <a
                        href="{{ url('/') }}"
                        class="rounded-lg px-3 py-2 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-100 hover:text-slate-900"
                    >
                        Home
                    </a>
                    @auth
                        <a
                            href="{{ route('dashboard') }}"
                            class="rounded-lg px-3 py-2 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-100 hover:text-slate-900"
                        >
                            Dashboard
                        </a>
                    @endauth
                    @auth
                        <a
                            href="{{ route('projects.index') }}"
                            class="rounded-lg px-3 py-2 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-100 hover:text-slate-900"
                        >
                            Projects
                        </a>
                    @else
                        <span
                            class="cursor-default rounded-lg px-3 py-2 text-sm font-medium text-slate-400"
                            title="Log in to manage projects"
                        >
                            Projects
                        </span>
                    @endauth
                    <span
                        class="cursor-default rounded-lg px-3 py-2 text-sm font-medium text-slate-400"
                        title="Coming in a later stage"
                    >
                        Tasks
                    </span>
                    <span
                        class="hidden cursor-default rounded-lg px-3 py-2 text-sm font-medium text-slate-400 sm:inline"
                        title="Coming in a later stage"
                    >
                        Team
                    </span>

                    @guest
                        <a
                            href="{{ route('login') }}"
                            class="rounded-lg px-3 py-2 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-100 hover:text-slate-900"
                        >
                            Log in
                        </a>
                        <a
                            href="{{ route('register') }}"
                            class="rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-500"
                        >
                            Register
                        </a>
                    @else
                        <span class="hidden px-2 text-sm text-slate-500 sm:inline">
                            {{ auth()->user()->name }}
                        </span>
                        <form method="POST" action="{{ route('logout') }}" class="inline">
                            @csrf
                            <button
                                type="submit"
                                class="rounded-lg px-3 py-2 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-100 hover:text-slate-900"
                            >
                                Log out
                            </button>
                        </form>
                    @endguest
                </nav>
            </div>
        </header>

        <main>
            @yield('content')
        </main>

        <x-confirm-modal />
    </body>
</html>
