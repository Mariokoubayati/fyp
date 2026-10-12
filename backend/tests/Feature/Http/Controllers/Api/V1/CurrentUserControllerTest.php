<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class CurrentUserControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_401_when_no_token_is_provided(): void
    {
        $this->getJson(route('api.v1.me'))->assertUnauthorized();
    }

    public function test_returns_401_when_token_is_malformed(): void
    {
        $this->withToken('not.a.valid-token')
            ->getJson(route('api.v1.me'))
            ->assertUnauthorized();
    }

    public function test_returns_200_with_the_authenticated_user_and_no_password(): void
    {
        $user = User::factory()->create(['name' => 'Sara Haddad', 'email' => 'sara@example.com']);

        $this->withToken(JWTAuth::fromUser($user))
            ->getJson(route('api.v1.me'))
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.name', 'Sara Haddad')
            ->assertJsonPath('data.email', 'sara@example.com')
            ->assertJsonMissingPath('data.password')
            ->assertJsonMissingPath('data.remember_token');
    }
}
