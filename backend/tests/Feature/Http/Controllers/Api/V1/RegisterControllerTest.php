<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegisterControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function validPayload(): array
    {
        return [
            'name' => 'Sara Haddad',
            'email' => 'sara@example.com',
            'password' => 'Sup3r-secret-pass',
        ];
    }

    public function test_valid_payload_creates_user_and_returns_201_with_token(): void
    {
        $response = $this->postJson(route('api.v1.auth.register'), $this->validPayload());

        $response->assertCreated()
            ->assertJsonPath('data.token_type', 'bearer')
            ->assertJsonPath('data.user.email', 'sara@example.com')
            ->assertJsonStructure(['data' => ['access_token', 'token_type', 'expires_in', 'user' => ['id', 'name', 'email']]])
            ->assertJsonMissingPath('data.user.password');

        $user = User::where('email', 'sara@example.com')->firstOrFail();
        $this->assertSame('Sara Haddad', $user->name);
        $this->assertTrue(Hash::check('Sup3r-secret-pass', $user->password));
    }

    public function test_issued_token_authenticates_the_new_user(): void
    {
        $token = $this->postJson(route('api.v1.auth.register'), $this->validPayload())
            ->json('data.access_token');

        $this->forgetAuthenticatedRequest();

        $this->withToken($token)
            ->getJson(route('api.v1.me'))
            ->assertOk()
            ->assertJsonPath('data.email', 'sara@example.com');
    }

    public function test_returns_422_when_payload_is_empty(): void
    {
        $this->postJson(route('api.v1.auth.register'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'password']);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_returns_422_when_email_is_already_taken(): void
    {
        User::factory()->create(['email' => 'sara@example.com']);

        $this->postJson(route('api.v1.auth.register'), $this->validPayload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email'])
            ->assertJsonPath('errors.email.0', 'The email has already been taken.');
    }

    public function test_returns_422_when_email_is_not_valid(): void
    {
        $this->postJson(route('api.v1.auth.register'), [...$this->validPayload(), 'email' => 'not-an-email'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_returns_422_when_password_is_too_short(): void
    {
        $this->postJson(route('api.v1.auth.register'), [...$this->validPayload(), 'password' => 'short'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);

        $this->assertDatabaseCount('users', 0);
    }
}
