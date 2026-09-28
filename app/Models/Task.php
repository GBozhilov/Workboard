<?php

namespace App\Models;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'title',
    'description',
    'status',
    'priority',
    'due_date',
])]
class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
            'priority' => TaskPriority::class,
            'due_date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * @param  Builder<Task>  $query
     */
    public function scopeSearch(Builder $query, ?string $search): void
    {
        if ($search === null || $search === '') {
            return;
        }

        $term = '%'.addcslashes($search, '%_\\').'%';

        $query->where(function (Builder $inner) use ($term): void {
            $inner->where('title', 'like', $term)
                ->orWhere('description', 'like', $term);
        });
    }

    /**
     * @param  Builder<Task>  $query
     */
    public function scopeFilterStatus(Builder $query, ?string $status): void
    {
        if ($status === null || $status === '') {
            return;
        }

        $query->where('status', $status);
    }

    /**
     * @param  Builder<Task>  $query
     */
    public function scopeFilterPriority(Builder $query, ?string $priority): void
    {
        if ($priority === null || $priority === '') {
            return;
        }

        $query->where('priority', $priority);
    }

    /**
     * @param  Builder<Task>  $query
     */
    public function scopeSorted(Builder $query, string $sort): void
    {
        match ($sort) {
            'oldest' => $query->orderBy('created_at'),
            'due_asc' => $query->orderByRaw('due_date IS NULL')->orderBy('due_date'),
            'due_desc' => $query->orderByRaw('due_date IS NOT NULL DESC')->orderByDesc('due_date'),
            'priority' => $query->orderByRaw(
                "CASE priority WHEN 'high' THEN 1 WHEN 'medium' THEN 2 WHEN 'low' THEN 3 ELSE 4 END"
            ),
            'title_asc' => $query->orderBy('title'),
            default => $query->orderByDesc('created_at'),
        };
    }
}
