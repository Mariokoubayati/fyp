<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class LogoutControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_401_when_no_token_is_provided(): void
    {
        $this->postJson(route('api.v1.auth.logout'))->assertUnauthorized();
    }

    public function test_logout_returns_204_and_invalidates_the_token(): void
    {
        $token = JWTAuth::fromUser(User::factory()->create());

        $this->withToken($token)
            ->postJson(route('api.v1.auth.logout'))
            ->assertNoContent();

        $this->forgetAuthenticatedRequest();

        $this->withToken($token)
            ->getJson(route('api.v1.me'))
            ->assertUnauthorized();
    }
}
