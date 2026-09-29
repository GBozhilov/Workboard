<?php

namespace App\Http\Requests;

use App\Models\Project;
use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskTagRequest extends FormRequest
{
    public const MAX_NAME_LENGTH = 50;

    public function authorize(): bool
    {
        $task = $this->route('task');

        return $task instanceof Task
            && $this->user()?->can('update', $task);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $project = $this->route('project');
        $projectId = $project instanceof Project ? $project->id : null;

        return [
            'name' => ['required_without:tag_id', 'nullable', 'string', 'max:'.self::MAX_NAME_LENGTH],
            'tag_id' => [
                'required_without:name',
                'nullable',
                'integer',
                Rule::exists('tags', 'id')->where(
                    fn ($query) => $projectId === null ? $query : $query->where('project_id', $projectId)
                ),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('name') && is_string($this->input('name'))) {
            $this->merge([
                'name' => trim($this->input('name')),
            ]);
        }
    }
}
