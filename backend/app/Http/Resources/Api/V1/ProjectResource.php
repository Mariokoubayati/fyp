<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Expects the membership aggregates from Project::withMembership() or loadMembership().
 * Pending requests are only shown to the owner.
 *
 * @mixin Project
 */
class ProjectResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isOwner = $this->owner_id === $request->user()?->id;

        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'skills_needed' => $this->skills_needed,
            'tags' => $this->tags->map->value->values(),
            'team_size' => $this->team_size,
            'members_count' => $this->members_count,
            'is_open' => $this->is_open,
            'is_full' => $this->isFull(),
            'my_status' => $this->my_status,
            'is_owner' => $isOwner,
            'created_at' => $this->created_at,
            'course' => new CourseResource($this->whenLoaded('course')),
            'owner' => $this->whenLoaded('owner', fn () => ['id' => $this->owner->id, 'name' => $this->owner->name]),
            'members' => $this->whenLoaded('members', fn () => $this->members->map(fn (User $member): array => [
                'id' => $member->id,
                'name' => $member->name,
                'major' => $member->major,
            ])->values()),
            'requests' => $this->when($isOwner && $this->relationLoaded('requests'), fn () => $this->requests->map(fn (User $student): array => [
                'id' => $student->id,
                'name' => $student->name,
                'major' => $student->major,
                'skills' => $student->skills ?? [],
                'message' => $student->pivot->message,
            ])->values()),
        ];
    }
}
