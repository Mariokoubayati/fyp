<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ListingStatus;
use App\Enums\TrustEventType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreListingRatingRequest;
use App\Models\Listing;
use App\Models\ListingRating;
use App\Services\Trust\TrustService;
use Illuminate\Http\JsonResponse;

class ListingRatingStoreController extends Controller
{
    /**
     * The buyer rates the seller once the sale is closed; the stars feed the seller's trust score.
     */
    public function __invoke(StoreListingRatingRequest $request, Listing $listing, TrustService $trust): JsonResponse
    {
        $user = $request->user();

        abort_unless($listing->status === ListingStatus::Sold && $listing->buyer_id === $user->id, 403, 'Only the buyer of a sold listing can rate it.');
        abort_if(ListingRating::where('listing_id', $listing->id)->exists(), 409, 'This sale was already rated.');

        $rating = new ListingRating($request->validated());
        $rating->listing()->associate($listing);
        $rating->rater_id = $user->id;
        $rating->ratee_id = $listing->seller_id;
        $rating->save();

        $trust->record($listing->seller, TrustEventType::RatingReceived, $listing, $rating->stars);

        return response()->json(['data' => ['stars' => $rating->stars, 'comment' => $rating->comment]], 201);
    }
}
