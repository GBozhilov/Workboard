@props(['for', 'value'])

<label {{ $attributes->merge(['class' => 'block text-sm font-medium text-slate-700']) }} for="{{ $for }}">
    {{ $value ?? $slot }}
</label>
