<?php

namespace Tests\Feature\Notifications;

use App\Enums\LoanStatus;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class LoanNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_borrower_is_notified_when_a_lender_accepts(): void
    {
        $loan = Loan::factory()->create();
        $lender = User::factory()->create(['name' => 'Lena']);

        $this->withToken(JWTAuth::fromUser($lender))->postJson(route('api.v1.loans.accept', $loan))->assertOk();

        $notification = $loan->borrower->notifications()->first();
        $this->assertSame('loan_accepted', $notification->type);
        $this->assertSame($loan->id, $notification->data['loan_id']);
        $this->assertSame('Lena', $notification->data['other']['name']);
    }

    public function test_borrowers_are_reminded_once_before_the_due_date(): void
    {
        $dueSoon = Loan::factory()->active()->create(['due_date' => today()->addDays(2)]);
        $later = Loan::factory()->active()->create(['due_date' => today()->addDays(10)]);

        $this->artisan('loans:process-due')->assertSuccessful();
        $this->artisan('loans:process-due')->assertSuccessful();

        $this->assertSame(1, $dueSoon->borrower->notifications()->where('type', 'loan_due_soon')->count());
        $this->assertSame(0, $later->borrower->notifications()->count());
    }

    public function test_the_borrower_is_notified_when_a_loan_goes_overdue(): void
    {
        $loan = Loan::factory()->active()->create(['due_date' => today()->subDay()]);

        $this->artisan('loans:process-due')->assertSuccessful();

        $this->assertSame(LoanStatus::Overdue, $loan->refresh()->status);
        $this->assertSame(1, $loan->borrower->notifications()->where('type', 'loan_overdue')->count());
    }
}
