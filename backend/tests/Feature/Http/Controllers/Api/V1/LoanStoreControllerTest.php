<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Enums\TrustEventType;
use App\Models\Loan;
use App\Models\User;
use App\Services\Trust\TrustService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class LoanStoreControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'amount' => 15,
            'purpose' => 'Printing my thesis',
            'due_date' => now()->addDays(10)->toDateString(),
            ...$overrides,
        ];
    }

    public function test_returns_401_when_no_token_is_provided(): void
    {
        $this->postJson(route('api.v1.loans.store'), $this->payload())->assertUnauthorized();
    }

    public function test_returns_403_when_the_email_is_not_verified(): void
    {
        $this->withToken(JWTAuth::fromUser(User::factory()->unverified()->create()))
            ->postJson(route('api.v1.loans.store'), $this->payload())
            ->assertForbidden();
    }

    public function test_returns_201_and_creates_a_requested_loan(): void
    {
        $borrower = User::factory()->create();

        $this->withToken(JWTAuth::fromUser($borrower))
            ->postJson(route('api.v1.loans.store'), $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.status', 'requested')
            ->assertJsonPath('data.amount', 15)
            ->assertJsonPath('data.my_role', 'borrower')
            ->assertJsonPath('data.borrower.trust_tier', 'basic');

        $this->assertDatabaseHas('loans', ['borrower_id' => $borrower->id, 'status' => 'requested', 'lender_id' => null]);
    }

    public function test_returns_422_when_the_amount_exceeds_the_trust_tier_limit(): void
    {
        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->postJson(route('api.v1.loans.store'), $this->payload(['amount' => 50]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['amount'])
            ->assertJsonPath('errors.amount.0', 'Your trust tier allows loans up to $20.');
    }

    public function test_a_trusted_student_can_borrow_up_to_100(): void
    {
        $borrower = User::factory()->create();

        // Lift the score for real through the event log so the tier is derived, not faked.
        $events = [
            [TrustEventType::LoanRepaidOnTime, Loan::factory()->create()],
            [TrustEventType::LoanRepaidOnTime, Loan::factory()->create()],
            [TrustEventType::LoanRepaidOnTime, Loan::factory()->create()],
            [TrustEventType::LoanRepaidOnTime, Loan::factory()->create()],
            [TrustEventType::VouchReceived, User::factory()->create()],
            [TrustEventType::VouchReceived, User::factory()->create()],
        ];
        foreach ($events as [$type, $subject]) {
            app(TrustService::class)->record($borrower, $type, $subject);
        }

        $this->withToken(JWTAuth::fromUser($borrower))
            ->postJson(route('api.v1.loans.store'), $this->payload(['amount' => 100]))
            ->assertCreated();
    }

    public function test_returns_422_when_the_trust_tier_allows_no_lending(): void
    {
        $borrower = User::factory()->create();
        foreach (Loan::factory()->count(2)->create() as $loan) {
            app(TrustService::class)->record($borrower, TrustEventType::LoanDefaulted, $loan);
        }

        $this->withToken(JWTAuth::fromUser($borrower))
            ->postJson(route('api.v1.loans.store'), $this->payload(['amount' => 5]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.amount.0', 'Your trust score is too low to borrow right now.');
    }

    public function test_returns_422_when_the_student_has_an_overdue_loan(): void
    {
        $borrower = User::factory()->create();
        Loan::factory()->overdue()->create(['borrower_id' => $borrower->id]);

        $this->withToken(JWTAuth::fromUser($borrower))
            ->postJson(route('api.v1.loans.store'), $this->payload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_returns_422_when_the_student_has_too_many_open_loans(): void
    {
        $borrower = User::factory()->create();
        Loan::factory()->count(2)->create(['borrower_id' => $borrower->id]);

        $this->withToken(JWTAuth::fromUser($borrower))
            ->postJson(route('api.v1.loans.store'), $this->payload())
            ->assertUnprocessable()
            ->assertJsonPath('errors.amount.0', 'You already have the maximum number of open loans.');
    }

    public function test_returns_422_when_the_payload_is_invalid(): void
    {
        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->postJson(route('api.v1.loans.store'), [
                'amount' => 2,
                'due_date' => now()->subDay()->toDateString(),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['amount', 'due_date']);
    }

    public function test_returns_422_when_the_due_date_is_too_far_away(): void
    {
        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->postJson(route('api.v1.loans.store'), $this->payload(['due_date' => now()->addDays(90)->toDateString()]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['due_date']);
    }
}
