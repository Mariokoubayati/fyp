<?php

namespace App\Policies;

use App\Models\Listing;
use App\Models\User;

class ListingPolicy
{
    /**
     * Only the seller can edit a listing.
     */
    public function update(User $user, Listing $listing): bool
    {
        return $user->is($listing->seller);
    }

    /**
     * Only the seller can remove a listing.
     */
    public function delete(User $user, Listing $listing): bool
    {
        return $user->is($listing->seller);
    }
}
