<?php

namespace App\Http\Requests\Api;

use App\Http\Requests\Api\Concerns\PaginatesApiRequests;
use App\Http\Requests\IndexProjectRequest;
use Illuminate\Foundation\Http\FormRequest;

class IndexApiProjectRequest extends FormRequest
{
    use PaginatesApiRequests;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge([
            'search' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', 'string', 'max:32'],
            'page' => ['nullable', 'integer', 'min:1'],
        ], $this->perPageRules());
    }

    public function searchTerm(): ?string
    {
        $search = trim((string) $this->input('search', ''));

        return $search === '' ? null : $search;
    }

    public function sort(): string
    {
        $sort = $this->input('sort');

        return in_array($sort, IndexProjectRequest::sortOptions(), true)
            ? $sort
            : IndexProjectRequest::SORT_NEWEST;
    }
}
