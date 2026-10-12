<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\Loan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class LoanConfirmHandoverControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_401_when_no_token_is_provided(): void
    {
        $this->postJson(route('api.v1.loans.confirm-handover', Loan::factory()->accepted()->create()))->assertUnauthorized();
    }

    public function test_one_confirmation_keeps_the_loan_accepted(): void
    {
        $loan = Loan::factory()->accepted()->create();

        $this->withToken(JWTAuth::fromUser($loan->borrower))
            ->postJson(route('api.v1.loans.confirm-handover', $loan))
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted')
            ->assertJsonPath('data.handover.borrower_confirmed', true)
            ->assertJsonPath('data.handover.lender_confirmed', false);
    }

    public function test_both_confirmations_activate_the_loan(): void
    {
        $loan = Loan::factory()->accepted()->create();

        $this->withToken(JWTAuth::fromUser($loan->borrower))->postJson(route('api.v1.loans.confirm-handover', $loan))->assertOk();
        $this->forgetAuthenticatedRequest();
        $this->withToken(JWTAuth::fromUser($loan->lender))
            ->postJson(route('api.v1.loans.confirm-handover', $loan))
            ->assertOk()
            ->assertJsonPath('data.status', 'active');

        $this->assertNotNull($loan->refresh()->activated_at);
    }

    public function test_returns_403_for_someone_who_is_not_a_party(): void
    {
        $loan = Loan::factory()->accepted()->create();

        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->postJson(route('api.v1.loans.confirm-handover', $loan))
            ->assertForbidden();
    }

    public function test_returns_409_when_the_loan_is_not_waiting_for_a_handover(): void
    {
        $loan = Loan::factory()->active()->create();

        $this->withToken(JWTAuth::fromUser($loan->borrower))
            ->postJson(route('api.v1.loans.confirm-handover', $loan))
            ->assertConflict();
    }
}
