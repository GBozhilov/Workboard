<?php

namespace App\Http\Requests;

final class ProjectValidationRules
{
    /**
     * @return array<string, array<int, string>>
     */
    public static function attributes(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
