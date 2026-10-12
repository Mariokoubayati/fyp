<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class VerifyEmailControllerTest extends TestCase
{
    use RefreshDatabase;

    private function verificationUrl(User $user, ?string $hash = null): string
    {
        return URL::temporarySignedRoute('api.v1.auth.verification.verify', now()->addHour(), [
            'id' => $user->id,
            'hash' => $hash ?? sha1($user->email),
        ]);
    }

    public function test_returns_200_and_verifies_the_email(): void
    {
        $user = User::factory()->unverified()->create();

        $this->getJson($this->verificationUrl($user))->assertOk()->assertJsonPath('message', 'Email verified.');

        $this->assertTrue($user->refresh()->hasVerifiedEmail());
    }

    public function test_returns_403_when_the_link_is_not_signed(): void
    {
        $user = User::factory()->unverified()->create();

        $this->getJson(route('api.v1.auth.verification.verify', ['id' => $user->id, 'hash' => sha1($user->email)]))
            ->assertForbidden();

        $this->assertFalse($user->refresh()->hasVerifiedEmail());
    }

    public function test_returns_403_when_the_hash_does_not_match_the_email(): void
    {
        $user = User::factory()->unverified()->create();

        $this->getJson($this->verificationUrl($user, sha1('someone-else@ua.edu.lb')))->assertForbidden();

        $this->assertFalse($user->refresh()->hasVerifiedEmail());
    }

    public function test_returns_404_when_the_user_does_not_exist(): void
    {
        $url = URL::temporarySignedRoute('api.v1.auth.verification.verify', now()->addHour(), [
            'id' => 9999,
            'hash' => sha1('ghost@ua.edu.lb'),
        ]);

        $this->getJson($url)->assertNotFound();
    }

    public function test_returns_403_when_the_signed_link_has_expired(): void
    {
        $user = User::factory()->unverified()->create();
        $url = URL::temporarySignedRoute('api.v1.auth.verification.verify', now()->subMinute(), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);

        $this->getJson($url)->assertForbidden();
    }
}
