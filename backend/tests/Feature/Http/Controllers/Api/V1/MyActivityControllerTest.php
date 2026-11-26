<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Enums\Badge;
use App\Models\Event;
use App\Models\Listing;
use App\Models\Loan;
use App\Models\StudyGroup;
use App\Models\User;
use App\Models\UserBadge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class MyActivityControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_401_when_no_token_is_provided(): void
    {
        $this->getJson(route('api.v1.me.activity'))->assertUnauthorized();
    }

    public function test_sums_the_students_own_activity_and_lists_every_badge(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();
        Listing::factory()->soldTo($other)->create(['seller_id' => $me->id, 'price' => 12, 'sold_at' => now()]);
        Listing::factory()->soldTo($me)->create(['seller_id' => $other->id, 'price' => 5, 'sold_at' => now()]);
        Listing::factory()->create(['seller_id' => $me->id]);
        Listing::factory()->soldTo($other)->create(['price' => 99]);
        Loan::factory()->active()->create(['borrower_id' => $me->id, 'lender_id' => $other->id, 'amount' => 20]);
        StudyGroup::factory()->create(['creator_id' => $me->id]);
        Event::factory()->past()->create()->attendees()->attach($me);
        Event::factory()->create(['creator_id' => $me->id]);
        UserBadge::create(['user_id' => $me->id, 'badge' => Badge::FirstSale, 'earned_at' => now()]);

        $response = $this->withToken(JWTAuth::fromUser($me))->getJson(route('api.v1.me.activity'))->assertOk();

        $response->assertJsonPath('data.marketplace', ['sold' => 1, 'earned' => 12, 'bought' => 1, 'spent' => 5, 'for_sale' => 1])
            ->assertJsonPath('data.lending.borrowed', 1)
            ->assertJsonPath('data.lending.borrowed_amount', 20)
            ->assertJsonPath('data.lending.open', 1)
            ->assertJsonPath('data.groups.joined', 1)
            ->assertJsonPath('data.groups.started', 1)
            ->assertJsonPath('data.events.went', 1)
            ->assertJsonPath('data.events.upcoming', 1)
            ->assertJsonPath('data.events.hosted', 1)
            ->assertJsonCount(4, 'data.months')
            ->assertJsonPath('data.months.3.earned', 12)
            ->assertJsonPath('data.months.3.spent', 5)
            ->assertJsonPath('data.months.3.marketplace', 2)
            ->assertJsonCount(count(Badge::cases()), 'data.badges')
            ->assertJsonPath('data.badges.0.key', 'first_sale')
            ->assertJsonPath('data.badges.0.progress', 1);
        $this->assertNotNull($response->json('data.badges.0.earned_at'));
        $this->assertNull($response->json('data.badges.1.earned_at'));
    }
}
