<?php

namespace App\Http\Requests;

use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTaskRequest extends FormRequest
{
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
        return TaskValidationRules::attributes();
    }
}
