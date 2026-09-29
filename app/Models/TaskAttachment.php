<?php

namespace App\Models;

use Database\Factories\TaskAttachmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'task_id',
    'user_id',
    'original_name',
    'path',
    'mime_type',
    'size',
])]
class TaskAttachment extends Model
{
    /** @use HasFactory<TaskAttachmentFactory> */
    use HasFactory;

    public const STORAGE_DISK = 'local';

    /**
     * @var list<string>
     */
    public const PREVIEWABLE_IMAGE_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
    ];

    protected static function booted(): void
    {
        static::deleting(function (TaskAttachment $attachment): void {
            $attachment->deleteStoredFile();
        });
    }

    /**
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function deleteStoredFile(): void
    {
        if ($this->path === '') {
            return;
        }

        $disk = Storage::disk(self::STORAGE_DISK);

        if ($disk->exists($this->path)) {
            $disk->delete($this->path);
        }
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    public function isPreviewableImage(): bool
    {
        return in_array($this->mime_type, self::PREVIEWABLE_IMAGE_MIME_TYPES, true);
    }

    public function friendlyTypeLabel(): string
    {
        if ($this->isPreviewableImage()) {
            return match ($this->mime_type) {
                'image/jpeg' => 'JPEG',
                'image/png' => 'PNG',
                'image/webp' => 'WEBP',
                'image/gif' => 'GIF',
                default => 'Image',
            };
        }

        return match ($this->mime_type) {
            'application/pdf' => 'PDF',
            'text/plain' => 'TXT',
            'text/log', 'application/x-log' => 'LOG',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'DOC',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'XLS',
            default => 'FILE',
        };
    }

    public function humanReadableSize(): string
    {
        $bytes = (int) $this->size;

        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1024 * 1024) {
            return number_format($bytes / 1024, $bytes < 10_240 ? 1 : 0).' KB';
        }

        return number_format($bytes / (1024 * 1024), 1).' MB';
    }
}
