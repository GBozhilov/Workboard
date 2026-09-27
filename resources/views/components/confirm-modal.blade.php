<div
    id="workboard-confirm-modal"
    class="fixed inset-0 z-[100] hidden items-center justify-center p-4"
    role="dialog"
    aria-modal="true"
    aria-labelledby="workboard-confirm-title"
    aria-describedby="workboard-confirm-message"
>
    <div
        id="workboard-confirm-backdrop"
        class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm"
        aria-hidden="true"
    ></div>

    <div class="relative w-full max-w-md rounded-2xl border border-slate-200/80 bg-white p-6 shadow-xl">
        <h2 id="workboard-confirm-title" class="text-lg font-semibold text-slate-900"></h2>
        <p id="workboard-confirm-message" class="mt-2 text-sm leading-relaxed text-slate-600"></p>

        <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <button
                type="button"
                id="workboard-confirm-cancel"
                class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50"
            >
                Cancel
            </button>
            <button
                type="button"
                id="workboard-confirm-confirm"
                class="inline-flex items-center justify-center rounded-lg px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition"
            >
                Confirm
            </button>
        </div>
    </div>
</div>
