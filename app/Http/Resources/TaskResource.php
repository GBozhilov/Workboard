<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Task */
class TaskResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status?->value,
            'priority' => $this->priority?->value,
            'due_date' => $this->due_date?->toDateString(),
            'assignee' => UserResource::make($this->whenLoaded('assignee')),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'comments_count' => $this->when(isset($this->comments_count), $this->comments_count),
            'attachments_count' => $this->when(isset($this->attachments_count), $this->attachments_count),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
