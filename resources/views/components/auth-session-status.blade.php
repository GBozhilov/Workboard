@props(['status'])

@if ($status)
    <div
        role="status"
        aria-live="polite"
        {{ $attributes->merge(['class' => 'rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-800']) }}
    >
        {{ $status }}
    </div>
@endif
