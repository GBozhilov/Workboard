@props(['project', 'listTask'])

@php
    $taskShowUrl = route('projects.tasks.show', [
        'project' => $project->getKey(),
        'task' => $listTask->getKey(),
    ]);
@endphp

<li>
    <a
        href="{{ $taskShowUrl }}"
        data-task-row-link
        data-task-id="{{ $listTask->getKey() }}"
        data-task-row-title="{{ $listTask->title }}"
        class="group flex w-full cursor-pointer flex-col gap-3 rounded-lg py-4 transition hover:bg-slate-50/80 sm:flex-row sm:items-center sm:justify-between focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2"
    >
        <div class="min-w-0">
            <span class="font-medium text-slate-900 transition group-hover:text-indigo-600">
                {{ $listTask->title }}
            </span>
            <p class="mt-1 min-h-4 text-xs leading-4 text-slate-500">
                @if ($listTask->due_date)
                    Due {{ $listTask->due_date->format('M j, Y') }}
                @endif
            </p>
            @if ($listTask->tags->isNotEmpty())
                <div class="mt-2 flex flex-wrap items-center gap-1">
                    @foreach ($listTask->tags->take(3) as $taskTag)
                        <x-task-tag-badge :tag="$taskTag" />
                    @endforeach
                    @if ($listTask->tags->count() > 3)
                        <span class="text-xs font-medium text-slate-500">+{{ $listTask->tags->count() - 3 }}</span>
                    @endif
                </div>
            @endif
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <x-task-status-badge :status="$listTask->status" />
            <x-task-priority-badge :priority="$listTask->priority" />
        </div>
    </a>
</li>
