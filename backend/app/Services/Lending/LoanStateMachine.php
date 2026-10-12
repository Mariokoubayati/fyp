<?php

namespace App\Services\Lending;

use App\Enums\LoanStatus;
use App\Exceptions\InvalidLoanTransition;
use App\Models\Loan;

/**
 * The only place a loan's status changes, so no invalid transition can slip through.
 */
class LoanStateMachine
{
    /**
     * @var array<string, array<int, LoanStatus>>
     */
    private const TRANSITIONS = [
        'requested' => [LoanStatus::Accepted, LoanStatus::Cancelled],
        'accepted' => [LoanStatus::Active, LoanStatus::Cancelled],
        'active' => [LoanStatus::Repaid, LoanStatus::Overdue],
        'overdue' => [LoanStatus::Repaid, LoanStatus::Defaulted],
        'defaulted' => [LoanStatus::Repaid],
        'repaid' => [],
        'cancelled' => [],
    ];

    public function can(Loan $loan, LoanStatus $to): bool
    {
        return in_array($to, self::TRANSITIONS[$loan->status->value], true);
    }

    /**
     * Move the loan to a new status (the caller saves it).
     *
     * @throws InvalidLoanTransition
     */
    public function transition(Loan $loan, LoanStatus $to): void
    {
        if (! $this->can($loan, $to)) {
            throw new InvalidLoanTransition($loan->status, $to);
        }

        $loan->status = $to;
    }
}
