<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Enums\ListingStatus;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class ListingUpdateControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_401_when_no_token_is_provided(): void
    {
        $listing = Listing::factory()->create();

        $this->patchJson(route('api.v1.listings.update', $listing), ['price' => 10])->assertUnauthorized();
    }

    public function test_returns_200_when_the_seller_edits_the_listing(): void
    {
        $listing = Listing::factory()->create(['price' => 30]);

        $this->withToken(JWTAuth::fromUser($listing->seller))
            ->patchJson(route('api.v1.listings.update', $listing), ['price' => 20, 'status' => 'reserved'])
            ->assertOk()
            ->assertJsonPath('data.price', 20)
            ->assertJsonPath('data.status', 'reserved');
    }

    public function test_returns_403_when_someone_else_edits_the_listing(): void
    {
        $listing = Listing::factory()->create(['price' => 30]);

        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->patchJson(route('api.v1.listings.update', $listing), ['price' => 1])
            ->assertForbidden();

        $this->assertEquals(30, $listing->fresh()->price);
    }

    public function test_cannot_mark_a_listing_sold_through_the_update_endpoint(): void
    {
        $listing = Listing::factory()->create();

        $this->withToken(JWTAuth::fromUser($listing->seller))
            ->patchJson(route('api.v1.listings.update', $listing), ['status' => 'sold'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

        $this->assertSame(ListingStatus::Available, $listing->fresh()->status);
    }

    public function test_returns_409_when_the_listing_is_already_sold(): void
    {
        $listing = Listing::factory()->soldTo(User::factory()->create())->create();

        $this->withToken(JWTAuth::fromUser($listing->seller))
            ->patchJson(route('api.v1.listings.update', $listing), ['price' => 1])
            ->assertConflict();
    }

    public function test_returns_422_when_values_are_invalid(): void
    {
        $listing = Listing::factory()->create();

        $this->withToken(JWTAuth::fromUser($listing->seller))
            ->patchJson(route('api.v1.listings.update', $listing), ['price' => 'free', 'category' => 'cars'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['price', 'category']);
    }
}
