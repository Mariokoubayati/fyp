<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ListingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\MarkListingSoldRequest;
use App\Http\Resources\Api\V1\ListingResource;
use App\Models\Listing;

class ListingMarkSoldController extends Controller
{
    /**
     * Close the sale and record who bought the item.
     */
    public function __invoke(MarkListingSoldRequest $request, Listing $listing): ListingResource
    {
        abort_if($listing->status === ListingStatus::Sold, 409, 'This listing is already sold.');

        $listing->forceFill([
            'status' => ListingStatus::Sold,
            'buyer_id' => $request->integer('buyer_id'),
            'sold_at' => now(),
        ])->save();

        return new ListingResource($listing->load(['seller', 'course']));
    }
}
