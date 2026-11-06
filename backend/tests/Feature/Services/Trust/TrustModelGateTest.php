<?php

namespace Tests\Feature\Services\Trust;

use App\Enums\TrustEventType;
use App\Models\Loan;
use App\Models\User;
use App\Models\Vouch;
use App\Services\Trust\TrustService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class TrustModelGateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.trust_model.enabled' => true,
            'services.trust_model.url' => 'http://ml.test',
            'services.trust_model.token' => 'secret',
        ]);
    }

    /**
     * A student the rules put in the top tier: five on-time loans from five different lenders.
     */
    private function trustedStudent(): User
    {
        $student = User::factory()->create(['created_at' => now()->subMonths(2)]);

        foreach (Loan::factory()->active()->count(5)->create(['borrower_id' => $student->id]) as $loan) {
            $loan->forceFill(['status' => 'repaid', 'repaid_at' => now()->subDay()])->save();
            app(TrustService::class)->record($student, TrustEventType::LoanRepaidOnTime, $loan, counterparty: $loan->lender);
        }

        return $student;
    }

    /**
     * @param  array<int, bool>  $allowedByAmount
     */
    private function fakeModel(array $allowedByAmount): void
    {
        Http::fake([
            'ml.test/trust/assess' => function (Request $request) use ($allowedByAmount) {
                return Http::response([
                    'model_version' => 'trust-hgb-1',
                    'risk_threshold' => 0.3,
                    'assessments' => array_map(fn ($amount): array => [
                        'amount' => $amount,
                        'p_default' => $allowedByAmount[(int) $amount] ? 0.05 : 0.6,
                        'allowed' => $allowedByAmount[(int) $amount],
                    ], $request['amounts']),
                ]);
            },
        ]);
    }

    public function test_the_model_is_not_called_when_disabled(): void
    {
        config(['services.trust_model.enabled' => false]);
        Http::fake();

        $result = app(TrustService::class)->current($this->trustedStudent());

        $this->assertSame('trusted', $result->tier);
        $this->assertNull($result->model);
        Http::assertNothingSent();
    }

    public function test_the_model_agreeing_keeps_the_rule_tier(): void
    {
        $this->fakeModel([20 => true, 100 => true]);

        $result = app(TrustService::class)->current($this->trustedStudent());

        $this->assertSame('trusted', $result->tier);
        $this->assertNotContains('model_risk', $result->flags);
        $this->assertSame('trust-hgb-1', $result->model['version']);
    }

    public function test_a_risky_large_loan_lowers_trusted_to_basic_but_keeps_the_score(): void
    {
        $this->fakeModel([20 => true, 100 => false]);
        $student = $this->trustedStudent();

        $this->withToken(JWTAuth::fromUser($student))
            ->getJson(route('api.v1.me.trust'))
            ->assertOk()
            ->assertJsonPath('data.score', 700)
            ->assertJsonPath('data.tier', 'basic')
            ->assertJsonPath('data.max_loan', 20)
            ->assertJsonPath('data.flags', ['model_risk'])
            ->assertJsonPath('data.model.version', 'trust-hgb-1');

        $this->assertSame(700, $student->refresh()->trust_score);
    }

    public function test_a_risky_small_loan_blocks_borrowing(): void
    {
        $this->fakeModel([20 => false]);
        $student = User::factory()->create(['created_at' => now()->subMonths(2)]);

        $this->withToken(JWTAuth::fromUser($student))
            ->postJson(route('api.v1.loans.store'), [
                'amount' => 10,
                'purpose' => 'Books',
                'due_date' => now()->addDays(10)->toDateString(),
            ])
            ->assertUnprocessable();

        $this->assertSame('restricted', app(TrustService::class)->current($student)->tier);
    }

    public function test_the_model_can_never_raise_a_tier(): void
    {
        $this->fakeModel([20 => true, 100 => true]);
        $student = User::factory()->create(['created_at' => now()->subMonths(2)]);

        $result = app(TrustService::class)->current($student);

        $this->assertSame('basic', $result->tier);
        Http::assertSent(fn (Request $request): bool => $request['amounts'] === [20]);
    }

    public function test_when_the_service_is_down_the_rules_decide_and_it_is_not_retried_for_a_minute(): void
    {
        Http::fake(['ml.test/*' => Http::response('boom', 500)]);
        $student = $this->trustedStudent();

        $first = app(TrustService::class)->current($student);
        $second = app(TrustService::class)->current($student);

        $this->assertSame('trusted', $first->tier);
        $this->assertNull($first->model);
        $this->assertSame('trusted', $second->tier);
        Http::assertSentCount(1);
    }

    public function test_the_service_receives_the_shared_history_format_with_the_token(): void
    {
        $this->fakeModel([20 => true, 100 => true]);
        $student = $this->trustedStudent();
        $friend = User::factory()->create();
        Vouch::forceCreate(['voucher_id' => $friend->id, 'vouchee_id' => $student->id]);
        Vouch::forceCreate(['voucher_id' => $student->id, 'vouchee_id' => $friend->id]);

        app(TrustService::class)->current($student);

        Http::assertSent(function (Request $request) use ($student, $friend): bool {
            $history = $request['history'];

            return $request->hasHeader('Authorization', 'Bearer secret')
                && $history['account_created_at'] === $student->created_at->getTimestamp()
                && $history['email_verified'] === true
                && count($history['borrowed']) === 5
                && $history['borrowed'][0]['repaid_at'] !== null
                && $history['vouches'] === [['voucher_id' => $friend->id, 'mutual' => true, 'eligible' => true]]
                && $history['circular_lending'] === false
                && $request['amounts'] === [20, 100];
        });
    }
}
