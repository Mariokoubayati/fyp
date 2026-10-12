<?php

namespace Tests\Unit\Services\Trust;

use App\Enums\TrustEventType;
use App\Models\TrustEvent;
use App\Models\User;
use App\Services\Trust\TrustScoreCalculator;
use Illuminate\Support\Collection;
use Tests\TestCase;

class TrustScoreCalculatorTest extends TestCase
{
    private TrustScoreCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = new TrustScoreCalculator;
    }

    private function verifiedStudent(): User
    {
        return User::factory()->make(['email_verified_at' => now(), 'created_at' => now()]);
    }

    /**
     * @param  array<int, array{0: TrustEventType, 1?: int|null}>  $definitions
     * @return Collection<int, TrustEvent>
     */
    private function events(array $definitions): Collection
    {
        return collect($definitions)->map(
            fn (array $definition): TrustEvent => new TrustEvent([
                'type' => $definition[0],
                'value' => $definition[1] ?? null,
            ])
        );
    }

    public function test_new_verified_student_starts_just_above_the_baseline_in_the_basic_tier(): void
    {
        $result = $this->calculator->calculate($this->verifiedStudent(), collect(), now());

        $this->assertSame(525, $result->score);
        $this->assertSame('basic', $result->tier);
        $this->assertSame(20, $result->maxLoan);
    }

    public function test_unverified_student_starts_below_the_baseline(): void
    {
        $student = User::factory()->unverified()->make(['created_at' => now()]);

        $this->assertSame(475, $this->calculator->calculate($student, collect(), now())->score);
    }

    public function test_repaying_on_time_raises_the_score(): void
    {
        $before = $this->calculator->calculate($this->verifiedStudent(), collect(), now())->score;
        $after = $this->calculator->calculate(
            $this->verifiedStudent(),
            $this->events([[TrustEventType::LoanRepaidOnTime]]),
            now(),
        )->score;

        $this->assertSame($before + 40, $after);
    }

    public function test_going_overdue_lowers_the_score(): void
    {
        $before = $this->calculator->calculate($this->verifiedStudent(), collect(), now())->score;
        $after = $this->calculator->calculate(
            $this->verifiedStudent(),
            $this->events([[TrustEventType::LoanOverdue]]),
            now(),
        )->score;

        $this->assertSame($before - 60, $after);
    }

    public function test_a_default_can_push_a_student_into_the_restricted_tier(): void
    {
        $events = $this->events([
            [TrustEventType::LoanOverdue],
            [TrustEventType::LoanDefaulted],
            [TrustEventType::LoanOverdue],
            [TrustEventType::LoanDefaulted],
        ]);

        $result = $this->calculator->calculate($this->verifiedStudent(), $events, now());

        $this->assertSame(325, $result->score);
        $this->assertSame('restricted', $result->tier);
        $this->assertSame(0, $result->maxLoan);
    }

    public function test_each_component_is_capped(): void
    {
        $events = $this->events(array_fill(0, 20, [TrustEventType::LoanRepaidOnTime]));

        $result = $this->calculator->calculate($this->verifiedStudent(), $events, now());

        $this->assertSame(200, $result->breakdown['repayment']);
    }

    public function test_the_score_is_always_between_0_and_1000(): void
    {
        $best = $this->events([
            ...array_fill(0, 20, [TrustEventType::LoanRepaidOnTime]),
            ...array_fill(0, 20, [TrustEventType::SaleCompleted]),
            ...array_fill(0, 20, [TrustEventType::VouchReceived]),
            ...array_fill(0, 20, [TrustEventType::GroupJoined]),
        ]);
        $worst = $this->events(array_fill(0, 20, [TrustEventType::LoanDefaulted]));

        $old = User::factory()->make(['email_verified_at' => now(), 'created_at' => now()->subYears(2)]);
        $unverified = User::factory()->unverified()->make(['created_at' => now()]);

        $this->assertLessThanOrEqual(1000, $this->calculator->calculate($old, $best, now())->score);
        $this->assertGreaterThanOrEqual(0, $this->calculator->calculate($unverified, $worst, now())->score);
    }

    public function test_ratings_scale_with_the_number_of_stars(): void
    {
        $five = $this->calculator->calculate($this->verifiedStudent(), $this->events([[TrustEventType::RatingReceived, 5]]), now());
        $one = $this->calculator->calculate($this->verifiedStudent(), $this->events([[TrustEventType::RatingReceived, 1]]), now());

        $this->assertSame(20, $five->breakdown['marketplace']);
        $this->assertSame(-20, $one->breakdown['marketplace']);
    }

    public function test_account_age_adds_points_up_to_its_cap(): void
    {
        $sixtyDays = User::factory()->make(['email_verified_at' => now(), 'created_at' => now()->subDays(65)]);
        $veteran = User::factory()->make(['email_verified_at' => now(), 'created_at' => now()->subYears(3)]);

        $this->assertSame(25 + 10, $this->calculator->calculate($sixtyDays, collect(), now())->breakdown['account']);
        $this->assertSame(25 + 25, $this->calculator->calculate($veteran, collect(), now())->breakdown['account']);
    }

    public function test_tier_boundaries(): void
    {
        $this->assertSame('restricted', $this->calculator->tierFor(399)['name']);
        $this->assertSame('basic', $this->calculator->tierFor(400)['name']);
        $this->assertSame('basic', $this->calculator->tierFor(699)['name']);
        $this->assertSame('trusted', $this->calculator->tierFor(700)['name']);
        $this->assertSame(100, $this->calculator->tierFor(1000)['max_loan']);
    }
}
