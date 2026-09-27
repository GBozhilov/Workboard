@php
    use App\Enums\TaskPriority;
    use App\Enums\TaskStatus;

    $task = $task ?? null;
@endphp

<div class="space-y-6">
    <div>
        <x-input-label for="title" value="Title" />
        <x-text-input
            id="title"
            name="title"
            type="text"
            :value="old('title', $task?->title)"
            required
            autofocus
        />
        <x-input-error :messages="$errors->get('title')" />
    </div>

    <div>
        <x-input-label for="description" value="Description" />
        <textarea
            id="description"
            name="description"
            rows="4"
            class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
        >{{ old('description', $task?->description) }}</textarea>
        <x-input-error :messages="$errors->get('description')" />
    </div>

    <div class="grid gap-6 sm:grid-cols-2">
        <div>
            <x-input-label for="status" value="Status" />
            <select
                id="status"
                name="status"
                required
                class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
            >
                @foreach (TaskStatus::cases() as $status)
                    <option
                        value="{{ $status->value }}"
                        @selected(old('status', $task?->status?->value ?? TaskStatus::Todo->value) === $status->value)
                    >
                        {{ $status->label() }}
                    </option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('status')" />
        </div>

        <div>
            <x-input-label for="priority" value="Priority" />
            <select
                id="priority"
                name="priority"
                required
                class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
            >
                @foreach (TaskPriority::cases() as $priority)
                    <option
                        value="{{ $priority->value }}"
                        @selected(old('priority', $task?->priority?->value ?? TaskPriority::Medium->value) === $priority->value)
                    >
                        {{ $priority->label() }}
                    </option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('priority')" />
        </div>
    </div>

    <div>
        <x-input-label for="due_date" value="Due date" />
        <x-text-input
            id="due_date"
            name="due_date"
            type="date"
            :value="old('due_date', $task?->due_date?->format('Y-m-d'))"
        />
        <x-input-error :messages="$errors->get('due_date')" />
    </div>
</div>
