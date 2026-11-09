<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class RefreshTokenControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_401_when_no_token_is_provided(): void
    {
        $this->postJson(route('api.v1.auth.refresh'))->assertUnauthorized();
    }

    public function test_refresh_returns_new_token_and_invalidates_the_old_one(): void
    {
        $user = User::factory()->create();
        $oldToken = JWTAuth::fromUser($user);

        $response = $this->withToken($oldToken)
            ->postJson(route('api.v1.auth.refresh'))
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id);

        $newToken = $response->json('data.access_token');
        $this->assertNotSame($oldToken, $newToken);

        $this->forgetAuthenticatedRequest();
        $this->withToken($oldToken)->getJson(route('api.v1.me'))->assertUnauthorized();

        $this->forgetAuthenticatedRequest();
        $this->withToken($newToken)->getJson(route('api.v1.me'))->assertOk();
    }

    public function test_an_expired_token_can_still_be_refreshed(): void
    {
        $user = User::factory()->create();
        $token = JWTAuth::fromUser($user);

        $this->travel(config('jwt.ttl') + 5)->minutes();

        $this->withToken($token)->getJson(route('api.v1.me'))->assertUnauthorized();

        $this->forgetAuthenticatedRequest();
        $newToken = $this->withToken($token)
            ->postJson(route('api.v1.auth.refresh'))
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->json('data.access_token');

        $this->forgetAuthenticatedRequest();
        $this->withToken($newToken)->getJson(route('api.v1.me'))->assertOk();
    }

    public function test_a_token_past_the_refresh_window_cannot_be_refreshed(): void
    {
        $token = JWTAuth::fromUser(User::factory()->create());

        $this->travel(config('jwt.refresh_ttl') + 5)->minutes();

        $this->withToken($token)->postJson(route('api.v1.auth.refresh'))->assertUnauthorized();
    }

    public function test_a_garbage_token_cannot_be_refreshed(): void
    {
        $this->withToken('not-a-token')->postJson(route('api.v1.auth.refresh'))->assertUnauthorized();
    }
}
