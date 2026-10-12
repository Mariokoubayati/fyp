<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Enums\LoanStatus;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class LoanCancelControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_401_when_no_token_is_provided(): void
    {
        $this->postJson(route('api.v1.loans.cancel', Loan::factory()->create()))->assertUnauthorized();
    }

    public function test_borrower_can_withdraw_an_open_request(): void
    {
        $loan = Loan::factory()->create();

        $this->withToken(JWTAuth::fromUser($loan->borrower))
            ->postJson(route('api.v1.loans.cancel', $loan))
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
    }

    public function test_lender_can_back_out_of_an_accepted_loan(): void
    {
        $loan = Loan::factory()->accepted()->create();

        $this->withToken(JWTAuth::fromUser($loan->lender))
            ->postJson(route('api.v1.loans.cancel', $loan))
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
    }

    public function test_returns_403_for_a_stranger(): void
    {
        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->postJson(route('api.v1.loans.cancel', Loan::factory()->create()))
            ->assertForbidden();
    }

    public function test_returns_403_once_the_money_has_moved(): void
    {
        $loan = Loan::factory()->active()->create();

        $this->withToken(JWTAuth::fromUser($loan->borrower))
            ->postJson(route('api.v1.loans.cancel', $loan))
            ->assertForbidden();

        $this->assertSame(LoanStatus::Active, $loan->refresh()->status);
    }
}
