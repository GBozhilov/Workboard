<?php

namespace App\Http\Requests;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;

class FilterProjectTasksRequest extends FormRequest
{
    public const SORT_NEWEST = 'newest';

    public const SORT_OLDEST = 'oldest';

    public const SORT_DUE_ASC = 'due_asc';

    public const SORT_DUE_DESC = 'due_desc';

    public const SORT_PRIORITY = 'priority';

    public const SORT_TITLE_ASC = 'title_asc';

    public const STATUS_ALL = 'all';

    public const PRIORITY_ALL = 'all';

    /**
     * @return list<string>
     */
    public static function sortOptions(): array
    {
        return [
            self::SORT_NEWEST,
            self::SORT_OLDEST,
            self::SORT_DUE_ASC,
            self::SORT_DUE_DESC,
            self::SORT_PRIORITY,
            self::SORT_TITLE_ASC,
        ];
    }

    public function authorize(): bool
    {
        $project = $this->route('project');

        return $project instanceof Project
            && $this->user()?->can('view', $project);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:32'],
            'priority' => ['nullable', 'string', 'max:32'],
            'sort' => ['nullable', 'string', 'max:32'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['search', 'status', 'priority', 'sort'] as $key) {
            if ($this->has($key) && $this->input($key) === '') {
                $this->merge([$key => null]);
            }
        }
    }

    public function searchTerm(): ?string
    {
        $search = trim((string) $this->input('search', ''));

        return $search === '' ? null : $search;
    }

    public function statusFilter(): ?string
    {
        $status = $this->input('status', self::STATUS_ALL);

        if ($status === self::STATUS_ALL || $status === null) {
            return null;
        }

        return TaskStatus::tryFrom($status)?->value;
    }

    public function priorityFilter(): ?string
    {
        $priority = $this->input('priority', self::PRIORITY_ALL);

        if ($priority === self::PRIORITY_ALL || $priority === null) {
            return null;
        }

        return TaskPriority::tryFrom($priority)?->value;
    }

    public function sort(): string
    {
        $sort = $this->input('sort');

        return in_array($sort, self::sortOptions(), true)
            ? $sort
            : self::SORT_NEWEST;
    }

    public function hasActiveFilters(): bool
    {
        return $this->searchTerm() !== null
            || $this->statusFilter() !== null
            || $this->priorityFilter() !== null
            || ($this->input('sort') !== null && $this->sort() !== self::SORT_NEWEST);
    }
}
