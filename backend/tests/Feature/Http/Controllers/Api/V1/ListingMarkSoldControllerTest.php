<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Enums\ListingStatus;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class ListingMarkSoldControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_401_when_no_token_is_provided(): void
    {
        $listing = Listing::factory()->create();

        $this->postJson(route('api.v1.listings.sold', $listing), ['buyer_id' => 1])->assertUnauthorized();
    }

    public function test_returns_200_and_records_the_buyer(): void
    {
        $listing = Listing::factory()->create();
        $buyer = User::factory()->create();

        $this->withToken(JWTAuth::fromUser($listing->seller))
            ->postJson(route('api.v1.listings.sold', $listing), ['buyer_id' => $buyer->id])
            ->assertOk()
            ->assertJsonPath('data.status', 'sold');

        $listing->refresh();
        $this->assertSame(ListingStatus::Sold, $listing->status);
        $this->assertSame($buyer->id, $listing->buyer_id);
        $this->assertNotNull($listing->sold_at);
    }

    public function test_returns_403_when_someone_else_closes_the_sale(): void
    {
        $listing = Listing::factory()->create();
        $buyer = User::factory()->create();

        $this->withToken(JWTAuth::fromUser($buyer))
            ->postJson(route('api.v1.listings.sold', $listing), ['buyer_id' => $buyer->id])
            ->assertForbidden();

        $this->assertSame(ListingStatus::Available, $listing->fresh()->status);
    }

    public function test_returns_422_when_the_seller_names_themselves_as_buyer(): void
    {
        $listing = Listing::factory()->create();

        $this->withToken(JWTAuth::fromUser($listing->seller))
            ->postJson(route('api.v1.listings.sold', $listing), ['buyer_id' => $listing->seller_id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['buyer_id']);
    }

    public function test_returns_422_when_the_buyer_does_not_exist(): void
    {
        $listing = Listing::factory()->create();

        $this->withToken(JWTAuth::fromUser($listing->seller))
            ->postJson(route('api.v1.listings.sold', $listing), ['buyer_id' => 9999])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['buyer_id']);
    }

    public function test_returns_409_when_the_listing_is_already_sold(): void
    {
        $listing = Listing::factory()->soldTo(User::factory()->create())->create();

        $this->withToken(JWTAuth::fromUser($listing->seller))
            ->postJson(route('api.v1.listings.sold', $listing), ['buyer_id' => User::factory()->create()->id])
            ->assertConflict();
    }
}
