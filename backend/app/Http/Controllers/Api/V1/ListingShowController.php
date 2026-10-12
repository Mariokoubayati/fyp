<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ListingResource;
use App\Models\Listing;

class ListingShowController extends Controller
{
    /**
     * Show one listing.
     */
    public function __invoke(Listing $listing): ListingResource
    {
        return new ListingResource($listing->load(['seller', 'course']));
    }
}
