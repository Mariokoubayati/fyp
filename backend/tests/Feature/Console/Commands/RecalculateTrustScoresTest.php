<?php

namespace Tests\Feature\Console\Commands;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecalculateTrustScoresTest extends TestCase
{
    use RefreshDatabase;

    public function test_recomputes_every_score_and_logs_it(): void
    {
        $students = User::factory()->count(3)->create(['created_at' => now()->subDays(95)]);

        $this->artisan('trust:recalculate')->assertSuccessful();

        foreach ($students as $student) {
            $this->assertSame(500, $student->refresh()->trust_score);
            $this->assertSame(1, $student->trustScoreLogs()->count());
        }
    }
}
