@props(['role'])

@php
    use App\Enums\ProjectRole;
    $projectRole = $role instanceof ProjectRole ? $role : ProjectRole::from($role);
@endphp

<span
    {{ $attributes->class([
        'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset',
        'bg-indigo-50 text-indigo-800 ring-indigo-200' => $projectRole === ProjectRole::Owner,
        'bg-slate-100 text-slate-700 ring-slate-200' => $projectRole === ProjectRole::Member,
    ]) }}
>
    {{ $projectRole->label() }}
</span>
