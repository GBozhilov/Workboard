<?php

namespace App\Http\Requests\Api\Concerns;

trait PaginatesApiRequests
{
    public function perPage(int $default = 15): int
    {
        $perPage = (int) $this->input('per_page', $default);

        return min(100, max(1, $perPage));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function perPageRules(): array
    {
        return [
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
