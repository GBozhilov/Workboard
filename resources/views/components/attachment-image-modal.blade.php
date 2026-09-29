<div
    id="attachment-image-modal"
    class="fixed inset-0 z-[110] hidden items-center justify-center p-4"
    role="dialog"
    aria-modal="true"
    aria-labelledby="attachment-image-modal-title"
>
    <div
        id="attachment-image-modal-backdrop"
        class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm"
        aria-hidden="true"
    ></div>

    <div class="relative max-h-[90vh] w-full max-w-3xl rounded-2xl border border-slate-200/80 bg-white p-4 shadow-xl sm:p-6">
        <div class="flex items-start justify-between gap-4">
            <h2 id="attachment-image-modal-title" class="min-w-0 truncate text-sm font-semibold text-slate-900"></h2>
            <button
                type="button"
                id="attachment-image-modal-close"
                class="shrink-0 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50"
            >
                Close
            </button>
        </div>
        <div class="mt-4 flex max-h-[calc(90vh-6rem)] items-center justify-center overflow-auto rounded-lg bg-slate-50 p-2">
            <img
                id="attachment-image-modal-img"
                alt=""
                class="max-h-[calc(90vh-8rem)] max-w-full object-contain"
            />
        </div>
    </div>
</div>
