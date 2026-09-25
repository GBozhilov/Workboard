@props(['project' => null])

<div class="space-y-6">
    <div>
        <x-input-label for="name" value="Name" />
        <x-text-input
            id="name"
            name="name"
            type="text"
            :value="old('name', $project?->name)"
            required
            autofocus
        />
        <x-input-error :messages="$errors->get('name')" />
    </div>

    <div>
        <x-input-label for="description" value="Description" />
        <textarea
            id="description"
            name="description"
            rows="4"
            class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
        >{{ old('description', $project?->description) }}</textarea>
        <x-input-error :messages="$errors->get('description')" />
    </div>
</div>
