<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Expects the attendance aggregates from Event::withAttendance() or loadAttendance().
 *
 * @mixin Event
 */
class EventResource extends JsonResource
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
            'ends_at' => $this->ends_at,
            'capacity' => $this->capacity,
            'tags' => $this->tags->map->value->values(),
            'attendees_count' => $this->attendees_count,
            'is_full' => $this->isFull(),
            'is_going' => (bool) $this->is_going,
            'is_creator' => $this->creator_id === $request->user()?->id,
            'has_started' => $this->starts_at->isPast(),
            'course' => new CourseResource($this->whenLoaded('course')),
            'creator' => $this->whenLoaded('creator', fn () => ['id' => $this->creator->id, 'name' => $this->creator->name]),
        ];
    }
}
