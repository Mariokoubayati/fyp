<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class ListingRatingStoreControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_401_when_no_token_is_provided(): void
    {
        $this->postJson(route('api.v1.listings.rating.store', Listing::factory()->create()), ['stars' => 5])->assertUnauthorized();
    }

    public function test_returns_201_and_the_sellers_score_follows_the_stars(): void
    {
        $buyer = User::factory()->create();
        $listing = Listing::factory()->soldTo($buyer)->create();

        $this->withToken(JWTAuth::fromUser($buyer))
            ->postJson(route('api.v1.listings.rating.store', $listing), ['stars' => 5, 'comment' => 'Smooth deal'])
            ->assertCreated()
            ->assertJsonPath('data.stars', 5);

        $this->assertDatabaseHas('listing_ratings', ['listing_id' => $listing->id, 'rater_id' => $buyer->id, 'ratee_id' => $listing->seller_id, 'stars' => 5]);
        $this->assertSame(545, $listing->seller->refresh()->trust_score);
    }

    public function test_a_one_star_rating_lowers_the_sellers_score(): void
    {
        $buyer = User::factory()->create();
        $listing = Listing::factory()->soldTo($buyer)->create();

        $this->withToken(JWTAuth::fromUser($buyer))
            ->postJson(route('api.v1.listings.rating.store', $listing), ['stars' => 1])
            ->assertCreated();

        $this->assertSame(505, $listing->seller->refresh()->trust_score);
    }

    public function test_returns_403_when_someone_other_than_the_buyer_rates(): void
    {
        $listing = Listing::factory()->soldTo(User::factory()->create())->create();

        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->postJson(route('api.v1.listings.rating.store', $listing), ['stars' => 5])
            ->assertForbidden();
    }

    public function test_returns_403_when_the_listing_is_not_sold(): void
    {
        $listing = Listing::factory()->create();

        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->postJson(route('api.v1.listings.rating.store', $listing), ['stars' => 5])
            ->assertForbidden();
    }

    public function test_returns_409_when_the_sale_was_already_rated(): void
    {
        $buyer = User::factory()->create();
        $listing = Listing::factory()->soldTo($buyer)->create();
        $token = JWTAuth::fromUser($buyer);

        $this->withToken($token)->postJson(route('api.v1.listings.rating.store', $listing), ['stars' => 4])->assertCreated();
        $this->withToken($token)->postJson(route('api.v1.listings.rating.store', $listing), ['stars' => 1])->assertConflict();
    }

    public function test_returns_422_when_the_stars_are_out_of_range(): void
    {
        $buyer = User::factory()->create();
        $listing = Listing::factory()->soldTo($buyer)->create();

        $this->withToken(JWTAuth::fromUser($buyer))
            ->postJson(route('api.v1.listings.rating.store', $listing), ['stars' => 6])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['stars']);
    }
}
