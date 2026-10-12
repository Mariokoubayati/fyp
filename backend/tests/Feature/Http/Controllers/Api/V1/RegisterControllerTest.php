<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
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
            'email' => 'sara@ua.edu.lb',
            'password' => 'Sup3r-secret-pass',
        ];
    }

    public function test_valid_payload_creates_user_and_returns_201_with_token(): void
    {
        $response = $this->postJson(route('api.v1.auth.register'), $this->validPayload());

        $response->assertCreated()
            ->assertJsonPath('data.token_type', 'bearer')
            ->assertJsonPath('data.user.email', 'sara@ua.edu.lb')
            ->assertJsonStructure(['data' => ['access_token', 'token_type', 'expires_in', 'user' => ['id', 'name', 'email']]])
            ->assertJsonMissingPath('data.user.password');

        $user = User::where('email', 'sara@ua.edu.lb')->firstOrFail();
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
            ->assertJsonPath('data.email', 'sara@ua.edu.lb');
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
        User::factory()->create(['email' => 'sara@ua.edu.lb']);

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

    public function test_returns_422_when_email_is_not_a_university_address(): void
    {
        $this->postJson(route('api.v1.auth.register'), [...$this->validPayload(), 'email' => 'sara@gmail.com'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->assertDatabaseMissing('users', ['email' => 'sara@gmail.com']);
    }

    public function test_returns_422_when_email_only_looks_like_a_university_address(): void
    {
        $this->postJson(route('api.v1.auth.register'), [...$this->validPayload(), 'email' => 'sara@evilua.edu.lb.example.com'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_sends_a_verification_email_and_leaves_the_account_unverified(): void
    {
        Notification::fake();

        $this->postJson(route('api.v1.auth.register'), $this->validPayload())
            ->assertCreated()
            ->assertJsonPath('data.user.email_verified_at', null);

        Notification::assertSentTo(User::where('email', 'sara@ua.edu.lb')->firstOrFail(), VerifyEmail::class);
    }
}
