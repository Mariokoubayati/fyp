<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ListingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateListingRequest;
use App\Http\Resources\Api\V1\ListingResource;
use App\Models\Listing;

class ListingUpdateController extends Controller
{
    /**
     * Edit a listing (seller only, not once it is sold).
     */
    public function __invoke(UpdateListingRequest $request, Listing $listing): ListingResource
    {
        abort_if($listing->status === ListingStatus::Sold, 409, 'A sold listing cannot be edited.');

        $listing->update($request->validated());

        return new ListingResource($listing->load(['seller', 'course']));
    }
}
