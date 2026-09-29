@props(['tag'])

<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700 ring-1 ring-inset ring-slate-200/80']) }}>
    {{ $tag->name }}
</span>
