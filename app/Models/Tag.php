<?php

namespace App\Models;

use Database\Factories\TagFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

#[Fillable(['project_id', 'name', 'slug'])]
class Tag extends Model
{
    /** @use HasFactory<TagFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsToMany<Task, $this>
     */
    public function tasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class);
    }

    public static function findOrCreateForProject(Project $project, string $name): self
    {
        $normalized = trim($name);

        $existing = static::query()
            ->where('project_id', $project->id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($normalized)])
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $baseSlug = Str::slug($normalized);

        return static::create([
            'project_id' => $project->id,
            'name' => $normalized,
            'slug' => static::generateUniqueSlugForProject($project, $baseSlug),
        ]);
    }

    public static function generateUniqueSlugForProject(Project $project, string $baseSlug): string
    {
        $slug = $baseSlug !== '' ? $baseSlug : 'tag';
        $candidate = $slug;
        $suffix = 1;

        while (static::query()
            ->where('project_id', $project->id)
            ->where('slug', $candidate)
            ->exists()) {
            $candidate = $slug.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    /**
     * @param  Builder<Tag>  $query
     */
    public function scopeForProject(Builder $query, Project $project): void
    {
        $query->where('project_id', $project->id);
    }
}
