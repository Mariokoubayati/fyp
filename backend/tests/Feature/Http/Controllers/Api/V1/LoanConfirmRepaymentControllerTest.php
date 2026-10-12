<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Enums\LoanStatus;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class LoanConfirmRepaymentControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_401_when_no_token_is_provided(): void
    {
        $this->postJson(route('api.v1.loans.confirm-repayment', Loan::factory()->active()->create()))->assertUnauthorized();
    }

    public function test_one_confirmation_keeps_the_loan_active(): void
    {
        $loan = Loan::factory()->active()->create();

        $this->withToken(JWTAuth::fromUser($loan->lender))
            ->postJson(route('api.v1.loans.confirm-repayment', $loan))
            ->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.repayment.lender_confirmed', true);

        $this->assertSame(0, $loan->borrower->trustEvents()->count());
    }

    public function test_repaying_on_time_closes_the_loan_and_raises_the_borrowers_score(): void
    {
        $loan = Loan::factory()->active()->create();

        $this->withToken(JWTAuth::fromUser($loan->borrower))->postJson(route('api.v1.loans.confirm-repayment', $loan))->assertOk();
        $this->forgetAuthenticatedRequest();
        $this->withToken(JWTAuth::fromUser($loan->lender))
            ->postJson(route('api.v1.loans.confirm-repayment', $loan))
            ->assertOk()
            ->assertJsonPath('data.status', 'repaid');

        $this->assertDatabaseHas('trust_events', ['user_id' => $loan->borrower_id, 'type' => 'loan_repaid_on_time', 'subject_id' => $loan->id]);
        $this->assertSame(565, $loan->borrower->refresh()->trust_score);
    }

    public function test_repaying_an_overdue_loan_counts_as_late(): void
    {
        $loan = Loan::factory()->overdue()->create();

        $this->withToken(JWTAuth::fromUser($loan->borrower))->postJson(route('api.v1.loans.confirm-repayment', $loan))->assertOk();
        $this->forgetAuthenticatedRequest();
        $this->withToken(JWTAuth::fromUser($loan->lender))
            ->postJson(route('api.v1.loans.confirm-repayment', $loan))
            ->assertOk()
            ->assertJsonPath('data.status', 'repaid');

        $this->assertDatabaseHas('trust_events', ['user_id' => $loan->borrower_id, 'type' => 'loan_repaid_late']);
        $this->assertDatabaseMissing('trust_events', ['user_id' => $loan->borrower_id, 'type' => 'loan_repaid_on_time']);
    }

    public function test_returns_403_for_someone_who_is_not_a_party(): void
    {
        $loan = Loan::factory()->active()->create();

        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->postJson(route('api.v1.loans.confirm-repayment', $loan))
            ->assertForbidden();
    }

    public function test_returns_409_when_no_money_is_owed(): void
    {
        $loan = Loan::factory()->accepted()->create();

        $this->withToken(JWTAuth::fromUser($loan->borrower))
            ->postJson(route('api.v1.loans.confirm-repayment', $loan))
            ->assertConflict();
    }

    public function test_full_flow_request_accept_handover_repay_writes_a_trust_event(): void
    {
        $borrower = User::factory()->create();
        $lender = User::factory()->create();

        $loanId = $this->withToken(JWTAuth::fromUser($borrower))
            ->postJson(route('api.v1.loans.store'), ['amount' => 10, 'due_date' => now()->addDays(7)->toDateString()])
            ->assertCreated()
            ->json('data.id');
        $loan = Loan::findOrFail($loanId);

        $steps = [
            [$lender, 'api.v1.loans.accept'],
            [$borrower, 'api.v1.loans.confirm-handover'],
            [$lender, 'api.v1.loans.confirm-handover'],
            [$borrower, 'api.v1.loans.confirm-repayment'],
            [$lender, 'api.v1.loans.confirm-repayment'],
        ];

        foreach ($steps as [$actor, $routeName]) {
            $this->forgetAuthenticatedRequest();
            $this->withToken(JWTAuth::fromUser($actor))->postJson(route($routeName, $loan))->assertOk();
        }

        $this->assertSame(LoanStatus::Repaid, $loan->refresh()->status);
        $this->assertSame(1, $borrower->trustEvents()->where('type', 'loan_repaid_on_time')->count());
    }
}
