<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Enums\Badge;
use App\Enums\ListingCondition;
use App\Models\Listing;
use App\Models\User;
use App\Models\UserBadge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class ListingShowControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_401_when_no_token_is_provided(): void
    {
        $listing = Listing::factory()->create();

        $this->getJson(route('api.v1.listings.show', $listing))->assertUnauthorized();
    }

    public function test_returns_200_with_seller_and_flags(): void
    {
        $listing = Listing::factory()->create(['condition' => ListingCondition::LikeNew]);

        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->getJson(route('api.v1.listings.show', $listing))
            ->assertOk()
            ->assertJsonPath('data.id', $listing->id)
            ->assertJsonPath('data.condition', 'like_new')
            ->assertJsonPath('data.seller.id', $listing->seller_id)
            ->assertJsonPath('data.is_seller', false);
    }

    public function test_returns_404_when_the_listing_does_not_exist(): void
    {
        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->getJson(route('api.v1.listings.show', 9999))
            ->assertNotFound();
    }

    public function test_shows_whether_the_viewer_is_the_buyer_and_the_rating_given(): void
    {
        $buyer = User::factory()->create();
        $listing = Listing::factory()->soldTo($buyer)->create();

        $this->withToken(JWTAuth::fromUser($buyer))
            ->getJson(route('api.v1.listings.show', $listing))
            ->assertOk()
            ->assertJsonPath('data.is_buyer', true)
            ->assertJsonPath('data.rating', null);
    }

    public function test_shows_the_sellers_marketplace_badges_strongest_first(): void
    {
        $listing = Listing::factory()->create();
        foreach ([Badge::FirstSale, Badge::TopSeller, Badge::StudyBuddy] as $badge) {
            UserBadge::create(['user_id' => $listing->seller_id, 'badge' => $badge, 'earned_at' => now()]);
        }

        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->getJson(route('api.v1.listings.show', $listing))
            ->assertOk()
            ->assertJsonPath('data.seller.badges', [['key' => 'top_seller', 'title' => 'Top seller', 'icon' => 'star']]);
    }
}
