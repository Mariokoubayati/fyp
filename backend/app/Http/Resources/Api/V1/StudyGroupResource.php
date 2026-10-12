<?php

namespace App\Http\Resources\Api\V1;

use App\Models\StudyGroup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Expects the membership aggregates from StudyGroup::withMembership() or loadMembership().
 *
 * @mixin StudyGroup
 */
class StudyGroupResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'location' => $this->location,
            'starts_at' => $this->starts_at,
            'capacity' => $this->capacity,
            'members_count' => $this->members_count,
            'is_full' => $this->members_count >= $this->capacity,
            'is_member' => (bool) $this->is_member,
            'is_creator' => $this->creator_id === $request->user()?->id,
            'course' => new CourseResource($this->whenLoaded('course')),
            'creator' => $this->whenLoaded('creator', fn () => ['id' => $this->creator->id, 'name' => $this->creator->name]),
        ];
    }
}
