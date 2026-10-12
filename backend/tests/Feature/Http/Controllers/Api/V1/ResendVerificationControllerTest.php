<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class ResendVerificationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_401_when_no_token_is_provided(): void
    {
        $this->postJson(route('api.v1.auth.verification.resend'))->assertUnauthorized();
    }

    public function test_returns_202_and_sends_the_verification_email(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        $this->withToken(JWTAuth::fromUser($user))
            ->postJson(route('api.v1.auth.verification.resend'))
            ->assertAccepted();

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_does_not_send_an_email_when_already_verified(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->withToken(JWTAuth::fromUser($user))
            ->postJson(route('api.v1.auth.verification.resend'))
            ->assertAccepted();

        Notification::assertNothingSent();
    }
}
