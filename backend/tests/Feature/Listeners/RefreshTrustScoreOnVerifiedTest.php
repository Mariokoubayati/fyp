<?php

namespace Tests\Feature\Listeners;

use App\Models\User;
use App\Services\Trust\TrustService;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RefreshTrustScoreOnVerifiedTest extends TestCase
{
    use RefreshDatabase;

    public function test_verifying_the_email_opens_the_basic_tier(): void
    {
        $user = User::factory()->unverified()->create();
        $trust = app(TrustService::class);
        $this->assertSame('restricted', $trust->current($user)->tier);

        $user->markEmailAsVerified();
        event(new Verified($user));

        $this->assertSame(500, $user->refresh()->trust_score);
        $this->assertSame('basic', $trust->current($user)->tier);
        $this->assertSame(1, $user->trustScoreLogs()->count());
    }
}
