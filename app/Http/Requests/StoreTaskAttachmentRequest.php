<?php

namespace App\Http\Requests;

use App\Models\Task;
use App\Models\TaskAttachment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class StoreTaskAttachmentRequest extends FormRequest
{
    public const MAX_FILE_SIZE_KB = 10_240;

    public function authorize(): bool
    {
        $task = $this->route('task');

        return $task instanceof Task
            && $this->user()?->can('create', [TaskAttachment::class, $task]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                File::types([
                    'jpg',
                    'jpeg',
                    'png',
                    'webp',
                    'pdf',
                    'txt',
                    'log',
                    'doc',
                    'docx',
                    'xls',
                    'xlsx',
                ])
                    ->max(self::MAX_FILE_SIZE_KB),
            ],
        ];
    }
}
