<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ListingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreListingRequest;
use App\Http\Resources\Api\V1\ListingResource;
use App\Models\Listing;
use Illuminate\Http\JsonResponse;

class ListingStoreController extends Controller
{
    /**
     * Put an item up for sale.
     */
    public function __invoke(StoreListingRequest $request): JsonResponse
    {
        $listing = new Listing($request->validated());
        $listing->status = ListingStatus::Available;
        $listing->seller()->associate($request->user());
        $listing->save();

        return (new ListingResource($listing->load(['seller', 'course'])))
            ->response()
            ->setStatusCode(201);
    }
}
