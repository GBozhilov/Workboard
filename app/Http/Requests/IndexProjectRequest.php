<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IndexProjectRequest extends FormRequest
{
    public const SORT_NEWEST = 'newest';

    public const SORT_OLDEST = 'oldest';

    public const SORT_NAME_ASC = 'name_asc';

    public const SORT_NAME_DESC = 'name_desc';

    /**
     * @return list<string>
     */
    public static function sortOptions(): array
    {
        return [
            self::SORT_NEWEST,
            self::SORT_OLDEST,
            self::SORT_NAME_ASC,
            self::SORT_NAME_DESC,
        ];
    }

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', 'string', 'max:32'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function searchTerm(): ?string
    {
        $search = trim((string) $this->input('search', ''));

        return $search === '' ? null : $search;
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
            || ($this->input('sort') !== null && $this->sort() !== self::SORT_NEWEST);
    }
}
