@props(['status'])

<span
    {{ $attributes->class([
        'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset',
        'bg-slate-100 text-slate-700 ring-slate-200' => $status->value === 'todo',
        'bg-amber-50 text-amber-800 ring-amber-200' => $status->value === 'in_progress',
        'bg-emerald-50 text-emerald-800 ring-emerald-200' => $status->value === 'done',
    ]) }}
>
    {{ $status->label() }}
</span>
