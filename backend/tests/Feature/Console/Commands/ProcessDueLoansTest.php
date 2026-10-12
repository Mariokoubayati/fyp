<?php

namespace Tests\Feature\Console\Commands;

use App\Enums\LoanStatus;
use App\Models\Loan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcessDueLoansTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_active_loan_past_its_due_date_becomes_overdue_and_costs_trust(): void
    {
        $loan = Loan::factory()->active()->create(['due_date' => now()->subDay()->toDateString()]);

        $this->artisan('loans:process-due')->assertSuccessful();

        $this->assertSame(LoanStatus::Overdue, $loan->refresh()->status);
        $this->assertDatabaseHas('trust_events', ['user_id' => $loan->borrower_id, 'type' => 'loan_overdue']);
        $this->assertSame(465, $loan->borrower->refresh()->trust_score);
    }

    public function test_a_loan_due_today_is_not_overdue_yet(): void
    {
        $loan = Loan::factory()->active()->create(['due_date' => now()->toDateString()]);

        $this->artisan('loans:process-due')->assertSuccessful();

        $this->assertSame(LoanStatus::Active, $loan->refresh()->status);
    }

    public function test_an_overdue_loan_defaults_after_the_grace_period(): void
    {
        $loan = Loan::factory()->overdue()->create(['due_date' => now()->subDays(15)->toDateString()]);

        $this->artisan('loans:process-due')->assertSuccessful();

        $this->assertSame(LoanStatus::Defaulted, $loan->refresh()->status);
        $this->assertDatabaseHas('trust_events', ['user_id' => $loan->borrower_id, 'type' => 'loan_defaulted']);
    }

    public function test_an_overdue_loan_within_the_grace_period_stays_overdue(): void
    {
        $loan = Loan::factory()->overdue()->create(['due_date' => now()->subDays(5)->toDateString()]);

        $this->artisan('loans:process-due')->assertSuccessful();

        $this->assertSame(LoanStatus::Overdue, $loan->refresh()->status);
    }

    public function test_running_twice_does_not_double_penalise(): void
    {
        $loan = Loan::factory()->active()->create(['due_date' => now()->subDay()->toDateString()]);

        $this->artisan('loans:process-due')->assertSuccessful();
        $this->artisan('loans:process-due')->assertSuccessful();

        $this->assertSame(1, $loan->borrower->trustEvents()->where('type', 'loan_overdue')->count());
    }
}
