<?php

namespace Tests\Feature\Services\Trust;

use App\Enums\TrustEventType;
use App\Models\Listing;
use App\Models\User;
use App\Services\Trust\TrustService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrustServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_recording_an_event_updates_the_stored_score_and_logs_it(): void
    {
        $user = User::factory()->create();
        $listing = Listing::factory()->create();

        app(TrustService::class)->record($user, TrustEventType::SaleCompleted, $listing);

        $user->refresh();
        $this->assertSame(545, $user->trust_score);
        $this->assertDatabaseHas('trust_score_logs', ['user_id' => $user->id, 'score' => 545, 'trigger' => 'sale_completed']);
    }

    public function test_recording_the_same_event_twice_counts_once(): void
    {
        $user = User::factory()->create();
        $listing = Listing::factory()->create();
        $trust = app(TrustService::class);

        $trust->record($user, TrustEventType::SaleCompleted, $listing);
        $trust->record($user, TrustEventType::SaleCompleted, $listing);

        $this->assertSame(1, $user->trustEvents()->count());
        $this->assertSame(545, $user->refresh()->trust_score);
    }

    public function test_forgetting_an_event_takes_its_points_back(): void
    {
        $user = User::factory()->create();
        $voucher = User::factory()->create();
        $trust = app(TrustService::class);

        $trust->record($user, TrustEventType::VouchReceived, $voucher);
        $this->assertSame(550, $user->refresh()->trust_score);

        $trust->forget($user, TrustEventType::VouchReceived, $voucher);
        $this->assertSame(525, $user->refresh()->trust_score);
    }

    public function test_an_unchanged_score_is_not_logged_again(): void
    {
        $user = User::factory()->create();
        $trust = app(TrustService::class);

        $trust->refresh($user);
        $trust->refresh($user);

        $this->assertSame(1, $user->trustScoreLogs()->count());
    }
}
