<?php

namespace Tests\Unit\Services\Trust;

use App\Enums\TrustEventType;
use App\Models\TrustEvent;
use App\Models\User;
use App\Services\Trust\CollusionSignals;
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

    /**
     * A verified student past the new-account waiting period.
     */
    private function student(): User
    {
        return User::factory()->make(['email_verified_at' => now(), 'created_at' => now()->subMonths(2)]);
    }

    /**
     * @param  array<int, array{0: TrustEventType, 1?: int|null, 2?: int|null, 3?: int}>  $definitions
     *                                                                                                  [type, value, counterparty id, days ago]
     * @return Collection<int, TrustEvent>
     */
    private function events(array $definitions): Collection
    {
        return collect($definitions)->values()->map(
            fn (array $definition, int $index): TrustEvent => new TrustEvent([
                'type' => $definition[0],
                'value' => $definition[1] ?? null,
                'counterparty_id' => $definition[2] ?? null,
                'subject_id' => $index + 1,
                'occurred_at' => now()->subDays($definition[3] ?? 0)->addSeconds($index),
            ])
        );
    }

    /**
     * On-time repayments to the given lenders, each a few days apart.
     *
     * @param  list<int>  $lenderIds
     * @return Collection<int, TrustEvent>
     */
    private function repayments(array $lenderIds): Collection
    {
        return $this->events(array_map(
            fn (int $lender, int $i): array => [TrustEventType::LoanRepaidOnTime, null, $lender, 60 - $i * 3],
            $lenderIds,
            array_keys($lenderIds),
        ));
    }

    public function test_a_new_verified_student_starts_at_500_in_the_basic_tier(): void
    {
        $result = $this->calculator->calculate($this->student(), collect(), now());

        $this->assertSame(500, $result->score);
        $this->assertSame('basic', $result->tier);
        $this->assertSame(20, $result->maxLoan);
        $this->assertSame([], $result->flags);
    }

    public function test_an_unverified_student_cannot_borrow(): void
    {
        $student = User::factory()->unverified()->make(['created_at' => now()->subMonths(2)]);
        $result = $this->calculator->calculate($student, collect(), now());

        $this->assertSame(500, $result->score);
        $this->assertSame('restricted', $result->tier);
        $this->assertContains(TrustScoreCalculator::FLAG_UNVERIFIED, $result->flags);
    }

    public function test_repaying_three_different_lenders_earns_full_points(): void
    {
        $result = $this->calculator->calculate($this->student(), $this->repayments([11, 12, 13]), now());

        $this->assertSame(120, $result->breakdown['repayment']);
    }

    public function test_repaying_the_same_lender_earns_a_fraction(): void
    {
        $one = $this->calculator->calculate($this->student(), $this->repayments([11]), now());
        $two = $this->calculator->calculate($this->student(), $this->repayments([11, 12]), now());

        $this->assertSame(13, $one->breakdown['repayment']);
        $this->assertSame(53, $two->breakdown['repayment']);
    }

    public function test_only_two_rewards_per_partner_count_inside_the_window(): void
    {
        $five = $this->repayments([11, 11, 11, 11, 11]);

        $result = $this->calculator->calculate($this->student(), $five, now());

        // 2 counted x 40 x (1 lender / 3).
        $this->assertSame(27, $result->breakdown['repayment']);
    }

    public function test_the_same_partner_counts_again_after_the_window(): void
    {
        $events = $this->events([
            [TrustEventType::LoanRepaidOnTime, null, 11, 200],
            [TrustEventType::LoanRepaidOnTime, null, 11, 190],
            [TrustEventType::LoanRepaidOnTime, null, 11, 10],
        ]);

        $result = $this->calculator->calculate($this->student(), $events, now());

        $this->assertSame(40, $result->breakdown['repayment']);
    }

    public function test_penalties_are_never_capped_per_partner(): void
    {
        $events = $this->events([
            [TrustEventType::LoanOverdue, null, 11, 30],
            [TrustEventType::LoanOverdue, null, 11, 20],
            [TrustEventType::LoanOverdue, null, 11, 10],
        ]);

        $this->assertSame(-180, $this->calculator->calculate($this->student(), $events, now())->breakdown['repayment']);
    }

    public function test_discounted_events_stay_on_record_but_earn_nothing(): void
    {
        $events = $this->repayments([11, 12, 13]);
        $events->each(fn (TrustEvent $event) => $event->discount_reason = 'loan_too_short');

        $this->assertSame(0, $this->calculator->calculate($this->student(), $events, now())->breakdown['repayment']);
    }

    public function test_a_default_can_push_a_student_into_the_restricted_tier(): void
    {
        $events = $this->events([
            [TrustEventType::LoanOverdue, null, 11],
            [TrustEventType::LoanDefaulted, null, 11],
        ]);

        $result = $this->calculator->calculate($this->student(), $events, now());

        $this->assertSame(290, $result->score);
        $this->assertSame('restricted', $result->tier);
        $this->assertSame(0, $result->maxLoan);
    }

    public function test_each_component_is_capped(): void
    {
        $result = $this->calculator->calculate($this->student(), $this->repayments(range(11, 30)), now());

        $this->assertSame(300, $result->breakdown['repayment']);
    }

    public function test_the_score_is_always_between_0_and_1000(): void
    {
        $best = $this->repayments(range(11, 40))->concat($this->events([
            ...array_map(fn (int $id): array => [TrustEventType::SaleCompleted, null, $id], range(100, 120)),
            ...array_map(fn (int $id): array => [TrustEventType::VouchReceived, null, $id], range(200, 220)),
        ]));
        $worst = $this->events(array_fill(0, 20, [TrustEventType::LoanDefaulted, null, 11]));

        $this->assertLessThanOrEqual(1000, $this->calculator->calculate($this->student(), $best, now())->score);
        $this->assertGreaterThanOrEqual(0, $this->calculator->calculate($this->student(), $worst, now())->score);
    }

    public function test_ratings_scale_with_the_number_of_stars(): void
    {
        $five = $this->calculator->calculate($this->student(), $this->events([[TrustEventType::RatingReceived, 5, 11]]), now());
        $one = $this->calculator->calculate($this->student(), $this->events([[TrustEventType::RatingReceived, 1, 11]]), now());

        $this->assertSame(20, $five->breakdown['marketplace']);
        $this->assertSame(-20, $one->breakdown['marketplace']);
    }

    public function test_group_joins_no_longer_affect_the_score(): void
    {
        $events = $this->events(array_fill(0, 10, [TrustEventType::GroupJoined]));

        $this->assertSame(500, $this->calculator->calculate($this->student(), $events, now())->score);
    }

    public function test_mutual_vouches_count_half(): void
    {
        $events = $this->events([
            [TrustEventType::VouchReceived, null, 11],
            [TrustEventType::VouchReceived, null, 12],
        ]);

        $signals = new CollusionSignals(mutualVoucherIds: [11]);

        $this->assertSame(38, $this->calculator->calculate($this->student(), $events, now(), $signals)->breakdown['vouches']);
    }

    public function test_vouches_from_ineligible_students_count_nothing(): void
    {
        $events = $this->events([[TrustEventType::VouchReceived, null, 11]]);

        $signals = new CollusionSignals(ineligibleVoucherIds: [11]);

        $this->assertSame(0, $this->calculator->calculate($this->student(), $events, now(), $signals)->breakdown['vouches']);
    }

    public function test_a_new_account_cannot_reach_the_top_tier(): void
    {
        $newcomer = User::factory()->make(['email_verified_at' => now(), 'created_at' => now()->subDays(5)]);

        $result = $this->calculator->calculate($newcomer, $this->repayments([11, 12, 13, 14, 15]), now());

        $this->assertSame(700, $result->score);
        $this->assertSame('basic', $result->tier);
        $this->assertContains(TrustScoreCalculator::FLAG_NEW_ACCOUNT, $result->flags);
    }

    public function test_circular_lending_holds_the_tier_at_basic(): void
    {
        $signals = new CollusionSignals(circularLending: true);

        $result = $this->calculator->calculate($this->student(), $this->repayments([11, 12, 13, 14, 15]), now(), $signals);

        $this->assertSame(700, $result->score);
        $this->assertSame('basic', $result->tier);
        $this->assertContains(TrustScoreCalculator::FLAG_CIRCULAR_LENDING, $result->flags);
    }

    public function test_an_honest_borrower_reaches_the_top_tier(): void
    {
        $result = $this->calculator->calculate($this->student(), $this->repayments([11, 12, 13, 14, 15]), now());

        $this->assertSame(700, $result->score);
        $this->assertSame('trusted', $result->tier);
        $this->assertSame(100, $result->maxLoan);
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
