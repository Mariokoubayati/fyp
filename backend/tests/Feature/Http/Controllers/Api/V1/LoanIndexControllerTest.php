<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\Loan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class LoanIndexControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_401_when_no_token_is_provided(): void
    {
        $this->getJson(route('api.v1.loans.index'))->assertUnauthorized();
    }

    public function test_default_lists_open_requests_from_other_students_soonest_due_first(): void
    {
        $user = User::factory()->create();
        $later = Loan::factory()->create(['due_date' => now()->addDays(20)->toDateString()]);
        $sooner = Loan::factory()->create(['due_date' => now()->addDays(5)->toDateString()]);
        Loan::factory()->create(['borrower_id' => $user->id]);
        Loan::factory()->accepted()->create();

        $this->withToken(JWTAuth::fromUser($user))
            ->getJson(route('api.v1.loans.index'))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $sooner->id)
            ->assertJsonPath('data.1.id', $later->id);
    }

    public function test_borrowed_lists_only_the_students_own_borrowings(): void
    {
        $user = User::factory()->create();
        $mine = Loan::factory()->create(['borrower_id' => $user->id]);
        Loan::factory()->create();

        $this->withToken(JWTAuth::fromUser($user))
            ->getJson(route('api.v1.loans.index', ['role' => 'borrowed']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mine->id)
            ->assertJsonPath('data.0.my_role', 'borrower');
    }

    public function test_lent_lists_only_loans_the_student_funded(): void
    {
        $user = User::factory()->create();
        $funded = Loan::factory()->active()->create(['lender_id' => $user->id]);
        Loan::factory()->active()->create();

        $this->withToken(JWTAuth::fromUser($user))
            ->getJson(route('api.v1.loans.index', ['role' => 'lent']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $funded->id)
            ->assertJsonPath('data.0.my_role', 'lender');
    }
}
