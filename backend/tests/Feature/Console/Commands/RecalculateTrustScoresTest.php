<?php

namespace Tests\Feature\Console\Commands;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecalculateTrustScoresTest extends TestCase
{
    use RefreshDatabase;

    public function test_recomputes_scores_so_account_age_is_reflected(): void
    {
        $veteran = User::factory()->create(['created_at' => now()->subDays(95)]);

        $this->artisan('trust:recalculate')->assertSuccessful();

        $this->assertSame(500 + 25 + 15, $veteran->refresh()->trust_score);
    }
}
