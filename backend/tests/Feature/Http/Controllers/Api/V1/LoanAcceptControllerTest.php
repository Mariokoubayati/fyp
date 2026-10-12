<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Enums\LoanStatus;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class LoanAcceptControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_401_when_no_token_is_provided(): void
    {
        $this->postJson(route('api.v1.loans.accept', Loan::factory()->create()))->assertUnauthorized();
    }

    public function test_returns_200_and_records_the_lender(): void
    {
        $loan = Loan::factory()->create();
        $lender = User::factory()->create();

        $this->withToken(JWTAuth::fromUser($lender))
            ->postJson(route('api.v1.loans.accept', $loan))
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted')
            ->assertJsonPath('data.my_role', 'lender');

        $this->assertSame($lender->id, $loan->refresh()->lender_id);
    }

    public function test_returns_403_when_the_borrower_tries_to_fund_their_own_request(): void
    {
        $loan = Loan::factory()->create();

        $this->withToken(JWTAuth::fromUser($loan->borrower))
            ->postJson(route('api.v1.loans.accept', $loan))
            ->assertForbidden();

        $this->assertSame(LoanStatus::Requested, $loan->refresh()->status);
    }

    public function test_returns_403_when_the_email_is_not_verified(): void
    {
        $this->withToken(JWTAuth::fromUser(User::factory()->unverified()->create()))
            ->postJson(route('api.v1.loans.accept', Loan::factory()->create()))
            ->assertForbidden();
    }

    public function test_returns_409_when_another_lender_already_took_it(): void
    {
        $loan = Loan::factory()->accepted()->create();

        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->postJson(route('api.v1.loans.accept', $loan))
            ->assertConflict();
    }
}
