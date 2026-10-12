<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class ListingDestroyControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_401_when_no_token_is_provided(): void
    {
        $listing = Listing::factory()->create();

        $this->deleteJson(route('api.v1.listings.destroy', $listing))->assertUnauthorized();
    }

    public function test_returns_204_when_the_seller_deletes_the_listing(): void
    {
        $listing = Listing::factory()->create();

        $this->withToken(JWTAuth::fromUser($listing->seller))
            ->deleteJson(route('api.v1.listings.destroy', $listing))
            ->assertNoContent();

        $this->assertModelMissing($listing);
    }

    public function test_returns_403_when_someone_else_deletes_the_listing(): void
    {
        $listing = Listing::factory()->create();

        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->deleteJson(route('api.v1.listings.destroy', $listing))
            ->assertForbidden();

        $this->assertModelExists($listing);
    }
}
