@extends('layouts.app')

@section('title', 'Notifications — '.config('app.name'))

@section('content')
    <div class="mx-auto max-w-3xl px-4 py-12 sm:px-6 lg:px-8">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wider text-indigo-600">Inbox</p>
                <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-900">Notifications</h1>
            </div>
            @if (auth()->user()->unreadNotifications()->exists())
                <form method="POST" action="{{ route('notifications.read-all') }}">
                    @csrf
                    <button
                        type="submit"
                        class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50"
                    >
                        Mark all as read
                    </button>
                </form>
            @endif
        </div>

        <x-auth-session-status class="mt-6" :status="session('status')" />

        @if ($notifications->isEmpty())
            <p class="mt-10 rounded-2xl border border-dashed border-slate-300 bg-slate-50/80 px-6 py-10 text-center text-sm text-slate-600">
                No notifications yet.
            </p>
        @else
            <ul class="mt-8 divide-y divide-slate-100 rounded-2xl border border-slate-200/80 bg-white shadow-sm">
                @foreach ($notifications as $notification)
                    @php
                        $data = $notification->data;
                        $isUnread = $notification->read_at === null;
                    @endphp
                    <li @class([
                        'px-6 py-4',
                        'bg-indigo-50/40' => $isUnread,
                    ])>
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0 flex-1">
                                <p @class([
                                    'text-sm leading-relaxed text-slate-700',
                                    'font-semibold text-slate-900' => $isUnread,
                                ])>
                                    {{ $data['message'] ?? 'Notification' }}
                                </p>
                                <p class="mt-1 text-xs text-slate-500">
                                    {{ $notification->created_at->diffForHumans() }}
                                    @if ($isUnread)
                                        <span class="ml-2 inline-flex rounded-full bg-indigo-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-indigo-700">Unread</span>
                                    @endif
                                </p>
                            </div>
                            <div class="flex shrink-0 flex-wrap items-center gap-3">
                                <a
                                    href="{{ route('notifications.open', $notification) }}"
                                    class="text-sm font-semibold text-indigo-600 hover:text-indigo-500"
                                >
                                    View
                                </a>
                                @if ($isUnread)
                                    <form method="POST" action="{{ route('notifications.read', $notification) }}">
                                        @csrf
                                        <button
                                            type="submit"
                                            class="text-sm font-semibold text-slate-600 hover:text-slate-800"
                                        >
                                            Mark read
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>

            <x-listing-pagination :paginator="$notifications" />
        @endif
    </div>
@endsection
