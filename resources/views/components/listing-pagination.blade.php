@props(['paginator'])

@if ($paginator->hasPages())
    <nav class="mt-8 flex justify-center" aria-label="Pagination">
        <div class="flex flex-wrap items-center gap-1">
            @if ($paginator->onFirstPage())
                <span class="rounded-lg px-3 py-2 text-sm text-slate-400">Previous</span>
            @else
                <a
                    href="{{ $paginator->previousPageUrl() }}"
                    class="rounded-lg px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100"
                >
                    Previous
                </a>
            @endif

            <span class="px-3 py-2 text-sm text-slate-600">
                Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}
            </span>

            @if ($paginator->hasMorePages())
                <a
                    href="{{ $paginator->nextPageUrl() }}"
                    class="rounded-lg px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100"
                >
                    Next
                </a>
            @else
                <span class="rounded-lg px-3 py-2 text-sm text-slate-400">Next</span>
            @endif
        </div>
    </nav>
@endif
