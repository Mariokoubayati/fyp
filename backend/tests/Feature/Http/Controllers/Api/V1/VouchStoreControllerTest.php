<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Enums\TrustEventType;
use App\Models\Loan;
use App\Models\User;
use App\Models\Vouch;
use App\Services\Trust\TrustService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class VouchStoreControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_401_when_no_token_is_provided(): void
    {
        $this->putJson(route('api.v1.users.vouch.store', User::factory()->create()))->assertUnauthorized();
    }

    public function test_returns_204_and_raises_the_vouched_students_score(): void
    {
        $voucher = User::factory()->create();
        $student = User::factory()->create();

        $this->withToken(JWTAuth::fromUser($voucher))
            ->putJson(route('api.v1.users.vouch.store', $student))
            ->assertNoContent();

        $this->assertDatabaseHas('vouches', ['voucher_id' => $voucher->id, 'vouchee_id' => $student->id]);
        $this->assertSame(550, $student->refresh()->trust_score);
    }

    public function test_vouching_twice_counts_once(): void
    {
        $voucher = User::factory()->create();
        $student = User::factory()->create();
        $token = JWTAuth::fromUser($voucher);

        $this->withToken($token)->putJson(route('api.v1.users.vouch.store', $student))->assertNoContent();
        $this->withToken($token)->putJson(route('api.v1.users.vouch.store', $student))->assertNoContent();

        $this->assertSame(1, Vouch::count());
        $this->assertSame(550, $student->refresh()->trust_score);
    }

    public function test_returns_422_when_vouching_for_yourself(): void
    {
        $user = User::factory()->create();

        $this->withToken(JWTAuth::fromUser($user))
            ->putJson(route('api.v1.users.vouch.store', $user))
            ->assertUnprocessable();
    }

    public function test_returns_403_when_the_email_is_not_verified(): void
    {
        $this->withToken(JWTAuth::fromUser(User::factory()->unverified()->create()))
            ->putJson(route('api.v1.users.vouch.store', User::factory()->create()))
            ->assertForbidden();
    }

    public function test_returns_403_when_the_voucher_is_in_the_restricted_tier(): void
    {
        $voucher = User::factory()->create();
        foreach (Loan::factory()->count(2)->create() as $loan) {
            app(TrustService::class)->record($voucher, TrustEventType::LoanDefaulted, $loan);
        }

        $this->withToken(JWTAuth::fromUser($voucher))
            ->putJson(route('api.v1.users.vouch.store', User::factory()->create()))
            ->assertForbidden();
    }

    public function test_returns_422_after_ten_vouches_given(): void
    {
        $voucher = User::factory()->create();
        $token = JWTAuth::fromUser($voucher);

        foreach (User::factory()->count(10)->create() as $student) {
            $this->withToken($token)->putJson(route('api.v1.users.vouch.store', $student))->assertNoContent();
        }

        $this->withToken($token)
            ->putJson(route('api.v1.users.vouch.store', User::factory()->create()))
            ->assertUnprocessable();
    }
}
