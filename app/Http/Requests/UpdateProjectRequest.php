<?php

namespace App\Http\Requests;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');

        return $project instanceof Project
            && $this->user()?->can('update', $project);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return ProjectValidationRules::attributes();
    }
}
