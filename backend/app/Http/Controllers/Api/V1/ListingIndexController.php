<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ListingCategory;
use App\Enums\ListingStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ListingResource;
use App\Models\Listing;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ListingIndexController extends Controller
{
    /**
     * Browse available listings, or the student's own listings with ?mine=1.
     */
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $search = $request->string('search')->trim()->toString();

        $listings = Listing::query()
            ->with(['seller', 'course'])
            ->when(
                $request->boolean('mine'),
                fn ($query) => $query->where('seller_id', $user->id),
                fn ($query) => $query->where('status', ListingStatus::Available),
            )
            ->when($request->enum('category', ListingCategory::class), fn ($query, ListingCategory $category) => $query->where('category', $category))
            ->when($request->integer('course_id'), fn ($query, int $courseId) => $query->where('course_id', $courseId))
            ->when($request->filled('max_price'), fn ($query) => $query->where('price', '<=', $request->float('max_price')))
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('title', 'like', "%{$search}%")->orWhere('description', 'like', "%{$search}%");
            }))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return ListingResource::collection($listings);
    }
}
