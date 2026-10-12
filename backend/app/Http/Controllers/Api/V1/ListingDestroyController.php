<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ListingDestroyController extends Controller
{
    /**
     * Take a listing down (seller only).
     */
    public function __invoke(Listing $listing): Response
    {
        Gate::authorize('delete', $listing);

        $listing->delete();

        return response()->noContent();
    }
}
