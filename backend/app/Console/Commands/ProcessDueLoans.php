<?php

namespace App\Console\Commands;

use App\Enums\LoanStatus;
use App\Models\Loan;
use App\Services\Lending\LoanService;
use Illuminate\Console\Command;

class ProcessDueLoans extends Command
{
    /**
     * @var string
     */
    protected $signature = 'loans:process-due';

    /**
     * @var string
     */
    protected $description = 'Mark unpaid loans overdue after their due date, and defaulted after the grace period';

    /**
     * Execute the console command.
     */
    public function handle(LoanService $loans): int
    {
        $overdue = 0;
        $defaulted = 0;

        Loan::query()
            ->with('borrower')
            ->where('status', LoanStatus::Active)
            ->whereDate('due_date', '<', today())
            ->each(function (Loan $loan) use ($loans, &$overdue): void {
                $loans->markOverdue($loan);
                $overdue++;
            });

        Loan::query()
            ->with('borrower')
            ->where('status', LoanStatus::Overdue)
            ->whereDate('due_date', '<=', today()->subDays(config('lending.default_after_days')))
            ->each(function (Loan $loan) use ($loans, &$defaulted): void {
                $loans->markDefaulted($loan);
                $defaulted++;
            });

        $this->info("Marked {$overdue} loan(s) overdue and {$defaulted} defaulted.");

        return self::SUCCESS;
    }
}
