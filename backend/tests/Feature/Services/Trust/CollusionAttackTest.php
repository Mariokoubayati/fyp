<?php

namespace Tests\Feature\Services\Trust;

use App\Models\Loan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

/**
 * The supervisor's attack (memo point 1), run end to end through the real API:
 * two friends fake loans, sales, ratings and vouches to push each other into the $100 tier.
 */
class CollusionAttackTest extends TestCase
{
    use RefreshDatabase;

    private function student(): User
    {
        return User::factory()->create(['created_at' => now()->subMonths(2)]);
    }

    private function as(User $user): static
    {
        $this->forgetAuthenticatedRequest();

        return $this->withToken(JWTAuth::fromUser($user));
    }

    /**
     * One full fake loan: request, accept, both confirm handover, wait, both confirm repayment.
     */
    private function fakeLoan(User $borrower, User $lender, int $hoursBeforeRepaying): void
    {
        $loanId = $this->as($borrower)->postJson(route('api.v1.loans.store'), [
            'amount' => 5,
            'purpose' => 'Lunch',
            'due_date' => now()->addDays(10)->toDateString(),
        ])->assertCreated()->json('data.id');

        $loan = Loan::findOrFail($loanId);

        $this->as($lender)->postJson(route('api.v1.loans.accept', $loan))->assertOk();
        $this->as($lender)->postJson(route('api.v1.loans.confirm-handover', $loan))->assertOk();
        $this->as($borrower)->postJson(route('api.v1.loans.confirm-handover', $loan))->assertOk();

        $this->travel($hoursBeforeRepaying)->hours();

        $this->as($borrower)->postJson(route('api.v1.loans.confirm-repayment', $loan))->assertOk();
        $this->as($lender)->postJson(route('api.v1.loans.confirm-repayment', $loan))->assertOk()->assertJsonPath('data.status', 'repaid');
    }

    private function fakeSaleWithFiveStars(User $seller, User $buyer): void
    {
        $listingId = $this->as($seller)->postJson(route('api.v1.listings.store'), [
            'title' => 'Old notes',
            'price' => 1,
            'category' => 'books',
            'condition' => 'good',
        ])->assertCreated()->json('data.id');

        $this->as($seller)->postJson(route('api.v1.listings.sold', $listingId), ['buyer_id' => $buyer->id])->assertOk();
        $this->as($buyer)->postJson(route('api.v1.listings.rating.store', $listingId), ['stars' => 5])->assertCreated();
    }

    private function trustOf(User $user): array
    {
        return $this->as($user)->getJson(route('api.v1.me.trust'))->assertOk()->json('data');
    }

    public function test_one_friend_lending_to_the_other_many_times_cannot_reach_the_top_tier(): void
    {
        [$alice, $bob] = [$this->student(), $this->student()];

        for ($i = 0; $i < 6; $i++) {
            $this->fakeLoan(borrower: $alice, lender: $bob, hoursBeforeRepaying: 72);
        }
        for ($i = 0; $i < 3; $i++) {
            $this->fakeSaleWithFiveStars(seller: $alice, buyer: $bob);
        }
        $this->as($bob)->putJson(route('api.v1.users.vouch.store', $alice))->assertNoContent();

        $trust = $this->trustOf($alice);

        // Without the rules: 6x40 + 3x(20+20) + 25 = 385 points -> 885, trusted.
        // With them: 2 loans x 40 / 3 = 27, two marketplace rewards from Bob (2 sales) = 40,
        // one vouch 25 -> 592, basic.
        $this->assertSame(592, $trust['score']);
        $this->assertSame('basic', $trust['tier']);
        $this->assertSame(20, $trust['max_loan']);
    }

    public function test_two_friends_lending_back_and_forth_are_flagged_and_held_at_basic(): void
    {
        [$alice, $bob] = [$this->student(), $this->student()];

        for ($i = 0; $i < 4; $i++) {
            $this->fakeLoan(borrower: $alice, lender: $bob, hoursBeforeRepaying: 72);
            $this->fakeLoan(borrower: $bob, lender: $alice, hoursBeforeRepaying: 72);
        }
        $this->as($alice)->putJson(route('api.v1.users.vouch.store', $bob))->assertNoContent();
        $this->as($bob)->putJson(route('api.v1.users.vouch.store', $alice))->assertNoContent();

        foreach ([$alice, $bob] as $student) {
            $trust = $this->trustOf($student);

            $this->assertSame('basic', $trust['tier']);
            $this->assertContains('circular_lending', $trust['flags']);
            $this->assertLessThan(700, $trust['score']);
        }
    }

    public function test_a_ring_of_three_is_flagged(): void
    {
        [$alice, $bob, $carol] = [$this->student(), $this->student(), $this->student()];

        $this->fakeLoan(borrower: $bob, lender: $alice, hoursBeforeRepaying: 72);
        $this->fakeLoan(borrower: $carol, lender: $bob, hoursBeforeRepaying: 72);
        $this->fakeLoan(borrower: $alice, lender: $carol, hoursBeforeRepaying: 72);

        foreach ([$alice, $bob, $carol] as $student) {
            $this->assertContains('circular_lending', $this->trustOf($student)['flags']);
        }
    }

    public function test_loans_repaid_within_hours_earn_nothing(): void
    {
        [$alice, $bob, $carol, $dave] = [$this->student(), $this->student(), $this->student(), $this->student()];

        foreach ([$bob, $carol, $dave] as $lender) {
            $this->fakeLoan(borrower: $alice, lender: $lender, hoursBeforeRepaying: 1);
        }

        $trust = $this->trustOf($alice);

        $this->assertSame(500, $trust['score']);
        $this->assertSame(0, $trust['breakdown']['repayment']);
        $this->assertDatabaseHas('trust_events', ['user_id' => $alice->id, 'discount_reason' => 'loan_too_short']);
    }

    public function test_an_honest_borrower_with_five_different_lenders_reaches_the_top_tier(): void
    {
        $borrower = $this->student();

        for ($i = 0; $i < 5; $i++) {
            $this->fakeLoan(borrower: $borrower, lender: $this->student(), hoursBeforeRepaying: 72);
        }

        $trust = $this->trustOf($borrower);

        $this->assertSame(700, $trust['score']);
        $this->assertSame('trusted', $trust['tier']);
        $this->assertSame([], $trust['flags']);
    }
}
