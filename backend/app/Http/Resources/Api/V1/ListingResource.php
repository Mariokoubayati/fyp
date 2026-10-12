<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Listing;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Listing
 */
class ListingResource extends JsonResource
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
            'price' => (float) $this->price,
            'category' => $this->category,
            'condition' => $this->condition,
            'status' => $this->status,
            'sold_at' => $this->sold_at,
            'created_at' => $this->created_at,
            'is_seller' => $this->seller_id === $request->user()?->id,
            'course' => CourseResource::make($this->whenLoaded('course')),
            'seller' => $this->whenLoaded('seller', fn () => ['id' => $this->seller->id, 'name' => $this->seller->name]),
        ];
    }
}
