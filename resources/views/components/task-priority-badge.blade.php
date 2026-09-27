@props(['priority'])

<span
    {{ $attributes->class([
        'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset',
        'bg-sky-50 text-sky-800 ring-sky-200' => $priority->value === 'low',
        'bg-violet-50 text-violet-800 ring-violet-200' => $priority->value === 'medium',
        'bg-rose-50 text-rose-800 ring-rose-200' => $priority->value === 'high',
    ]) }}
>
    {{ $priority->label() }}
</span>
