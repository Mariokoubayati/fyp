<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_credentials_return_200_with_token_and_user(): void
    {
        $user = User::factory()->create(['email' => 'sara@example.com']);

        $this->postJson(route('api.v1.auth.login'), ['email' => 'sara@example.com', 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('data.token_type', 'bearer')
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonStructure(['data' => ['access_token', 'expires_in']]);
    }

    public function test_returns_401_when_password_is_wrong(): void
    {
        User::factory()->create(['email' => 'sara@example.com']);

        $this->postJson(route('api.v1.auth.login'), ['email' => 'sara@example.com', 'password' => 'wrong-password'])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Invalid email or password.')
            ->assertJsonMissingPath('data.access_token');
    }

    public function test_returns_401_when_email_is_unknown(): void
    {
        $this->postJson(route('api.v1.auth.login'), ['email' => 'nobody@example.com', 'password' => 'password'])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Invalid email or password.');
    }

    public function test_returns_422_when_payload_is_empty(): void
    {
        $this->postJson(route('api.v1.auth.login'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_returns_429_after_five_attempts_in_a_minute(): void
    {
        User::factory()->create(['email' => 'sara@example.com']);
        $attempt = ['email' => 'sara@example.com', 'password' => 'wrong-password'];

        for ($i = 0; $i < 5; $i++) {
            $this->postJson(route('api.v1.auth.login'), $attempt)->assertUnauthorized();
        }

        $this->postJson(route('api.v1.auth.login'), ['email' => 'sara@example.com', 'password' => 'password'])
            ->assertTooManyRequests();
    }
}
