<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Enums\TrustEventType;
use App\Models\Listing;
use App\Models\User;
use App\Services\Trust\TrustService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class MyTrustControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_401_when_no_token_is_provided(): void
    {
        $this->getJson(route('api.v1.me.trust'))->assertUnauthorized();
    }

    public function test_returns_score_tier_breakdown_and_history(): void
    {
        $user = User::factory()->create();
        app(TrustService::class)->record($user, TrustEventType::SaleCompleted, Listing::factory()->create());

        $this->withToken(JWTAuth::fromUser($user))
            ->getJson(route('api.v1.me.trust'))
            ->assertOk()
            ->assertJsonPath('data.score', 545)
            ->assertJsonPath('data.tier', 'basic')
            ->assertJsonPath('data.max_loan', 20)
            ->assertJsonPath('data.breakdown.marketplace', 20)
            ->assertJsonPath('data.breakdown.account', 25)
            ->assertJsonCount(1, 'data.history')
            ->assertJsonPath('data.history.0.score', 545);
    }
}
