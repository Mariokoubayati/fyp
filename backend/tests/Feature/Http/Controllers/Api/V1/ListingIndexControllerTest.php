<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Enums\ListingCategory;
use App\Models\Course;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class ListingIndexControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_401_when_no_token_is_provided(): void
    {
        $this->getJson(route('api.v1.listings.index'))->assertUnauthorized();
    }

    public function test_lists_only_available_listings_newest_first(): void
    {
        $older = Listing::factory()->create(['created_at' => now()->subDay()]);
        $newer = Listing::factory()->create();
        Listing::factory()->reserved()->create();
        Listing::factory()->soldTo(User::factory()->create())->create();

        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->getJson(route('api.v1.listings.index'))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $newer->id)
            ->assertJsonPath('data.1.id', $older->id);
    }

    public function test_mine_returns_all_of_the_students_listings_including_sold(): void
    {
        $user = User::factory()->create();
        Listing::factory()->create(['seller_id' => $user->id]);
        Listing::factory()->soldTo(User::factory()->create())->create(['seller_id' => $user->id]);
        Listing::factory()->create();

        $this->withToken(JWTAuth::fromUser($user))
            ->getJson(route('api.v1.listings.index', ['mine' => 1]))
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_search_matches_title_and_description(): void
    {
        Listing::factory()->create(['title' => 'Calculus textbook', 'description' => null]);
        Listing::factory()->create(['title' => 'Desk lamp', 'description' => 'Great for calculus homework']);
        Listing::factory()->create(['title' => 'Bicycle', 'description' => null]);

        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->getJson(route('api.v1.listings.index', ['search' => 'calculus']))
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_filters_by_category_course_and_max_price(): void
    {
        $course = Course::factory()->create();
        $match = Listing::factory()->create([
            'category' => ListingCategory::Books,
            'course_id' => $course->id,
            'price' => 20,
        ]);
        Listing::factory()->create(['category' => ListingCategory::Electronics, 'course_id' => $course->id, 'price' => 20]);
        Listing::factory()->create(['category' => ListingCategory::Books, 'price' => 20]);
        Listing::factory()->create(['category' => ListingCategory::Books, 'course_id' => $course->id, 'price' => 80]);

        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->getJson(route('api.v1.listings.index', ['category' => 'books', 'course_id' => $course->id, 'max_price' => 50]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $match->id);
    }
}
