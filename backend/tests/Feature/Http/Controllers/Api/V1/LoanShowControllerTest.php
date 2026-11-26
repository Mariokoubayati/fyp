<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Enums\Badge;
use App\Models\Loan;
use App\Models\User;
use App\Models\UserBadge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class LoanShowControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_401_when_no_token_is_provided(): void
    {
        $this->getJson(route('api.v1.loans.show', Loan::factory()->create()))->assertUnauthorized();
    }

    public function test_any_student_can_see_an_open_request(): void
    {
        $loan = Loan::factory()->create();

        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->getJson(route('api.v1.loans.show', $loan))
            ->assertOk()
            ->assertJsonPath('data.id', $loan->id)
            ->assertJsonPath('data.my_role', null)
            ->assertJsonPath('data.borrower.id', $loan->borrower_id);
    }

    public function test_participants_can_see_their_accepted_loan(): void
    {
        $loan = Loan::factory()->accepted()->create();

        $this->withToken(JWTAuth::fromUser($loan->lender))
            ->getJson(route('api.v1.loans.show', $loan))
            ->assertOk()
            ->assertJsonPath('data.my_role', 'lender')
            ->assertJsonPath('data.lender.id', $loan->lender_id);
    }

    public function test_returns_403_for_a_stranger_once_the_loan_is_no_longer_open(): void
    {
        $loan = Loan::factory()->accepted()->create();

        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->getJson(route('api.v1.loans.show', $loan))
            ->assertForbidden();
    }

    public function test_shows_the_borrower_and_lender_badges_that_matter_for_a_loan(): void
    {
        $loan = Loan::factory()->accepted()->create();
        UserBadge::create(['user_id' => $loan->borrower_id, 'badge' => Badge::ReliableBorrower, 'earned_at' => now()]);
        UserBadge::create(['user_id' => $loan->borrower_id, 'badge' => Badge::FirstSale, 'earned_at' => now()]);
        UserBadge::create(['user_id' => $loan->lender_id, 'badge' => Badge::HelpingHand, 'earned_at' => now()]);

        $this->withToken(JWTAuth::fromUser($loan->lender))
            ->getJson(route('api.v1.loans.show', $loan))
            ->assertOk()
            ->assertJsonPath('data.borrower.badges.*.key', ['reliable_borrower'])
            ->assertJsonPath('data.lender.badges.*.key', ['helping_hand']);
    }
}
