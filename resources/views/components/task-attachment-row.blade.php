@props(['project', 'task', 'attachment'])

<li class="flex flex-col gap-3 border-t border-slate-100 pt-4 first:border-t-0 first:pt-0 sm:flex-row sm:items-center sm:justify-between">
    <div
        @class([
            'group/preview relative min-w-0 flex-1 rounded-lg p-2 -m-2 transition-colors',
            'hover:bg-slate-50 focus-within:bg-slate-50 focus:outline-none',
        ])
        @if ($attachment->isPreviewableImage() && auth()->user()?->can('preview', $attachment))
            tabindex="0"
            role="button"
            data-attachment-row
            data-attachment-preview-url="{{ route('projects.tasks.attachments.preview', [$project, $task, $attachment]) }}"
            data-attachment-preview-image
            data-attachment-modal-filename="{{ $attachment->original_name }}"
        @else
            tabindex="0"
            data-attachment-row
            data-attachment-type-card
        @endif
    >
        <p class="truncate text-sm font-semibold text-slate-900">{{ $attachment->original_name }}</p>
        <p class="mt-1 text-xs text-slate-500">
            {{ $attachment->humanReadableSize() }}
            &middot;
            {{ $attachment->user->name }}
            &middot;
            {{ $attachment->created_at->format('M j, Y g:i A') }}
        </p>

        <div
            class="attachment-preview-popover pointer-events-none absolute bottom-full left-0 z-30 mb-2 hidden w-56 rounded-xl border border-slate-200/80 bg-white p-3 shadow-lg group-hover/preview:block group-focus-within/preview:block"
            aria-hidden="true"
        >
            @if ($attachment->isPreviewableImage() && auth()->user()?->can('preview', $attachment))
                <button
                    type="button"
                    class="attachment-preview-expand pointer-events-auto mb-2 block w-full overflow-hidden rounded-lg bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                    data-attachment-modal-trigger
                    data-attachment-preview-url="{{ route('projects.tasks.attachments.preview', [$project, $task, $attachment]) }}"
                    data-attachment-modal-filename="{{ $attachment->original_name }}"
                    aria-label="Open larger preview of {{ $attachment->original_name }}"
                >
                    <img
                        data-attachment-preview-img
                        alt=""
                        class="mx-auto max-h-40 w-full object-contain"
                        width="220"
                        height="160"
                    />
                </button>
            @else
                <div class="mb-2 flex h-24 items-center justify-center rounded-lg bg-slate-100">
                    <span class="rounded-md bg-indigo-100 px-3 py-1.5 text-sm font-bold tracking-wide text-indigo-800">
                        {{ $attachment->friendlyTypeLabel() }}
                    </span>
                </div>
            @endif
            <p class="truncate text-sm font-semibold text-slate-900">{{ $attachment->original_name }}</p>
            <p class="mt-1 text-xs text-slate-500">
                {{ $attachment->friendlyTypeLabel() }}
                &middot;
                {{ $attachment->humanReadableSize() }}
            </p>
        </div>
    </div>

    <div class="relative z-40 flex shrink-0 flex-wrap items-center gap-3">
        @can('download', $attachment)
            <a
                href="{{ route('projects.tasks.attachments.download', [$project, $task, $attachment]) }}"
                class="text-sm font-semibold text-indigo-600 hover:text-indigo-500"
            >
                Download
            </a>
        @endcan
        @can('delete', $attachment)
            <form
                method="POST"
                action="{{ route('projects.tasks.attachments.destroy', [$project, $task, $attachment]) }}"
                data-confirm="true"
                data-confirm-title="Delete attachment"
                data-confirm-message="Delete this file attachment?"
                data-confirm-label="Delete attachment"
                data-confirm-destructive
            >
                @csrf
                @method('DELETE')
                <button
                    type="submit"
                    class="text-sm font-semibold text-red-600 hover:text-red-500"
                >
                    Delete
                </button>
            </form>
        @endcan
    </div>
</li>
