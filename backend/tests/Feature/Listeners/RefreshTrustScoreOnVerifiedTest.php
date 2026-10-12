<?php

namespace Tests\Feature\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RefreshTrustScoreOnVerifiedTest extends TestCase
{
    use RefreshDatabase;

    public function test_verifying_the_email_lifts_the_score_from_unverified_to_verified(): void
    {
        $user = User::factory()->unverified()->create();
        $this->assertSame(500, $user->trust_score);

        $user->markEmailAsVerified();
        event(new Verified($user));

        $this->assertSame(525, $user->refresh()->trust_score);
    }
}
