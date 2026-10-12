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
}
