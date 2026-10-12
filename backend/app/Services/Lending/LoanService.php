<?php

namespace App\Services\Lending;

use App\Enums\LoanStatus;
use App\Enums\TrustEventType;
use App\Exceptions\InvalidLoanTransition;
use App\Models\Loan;
use App\Models\User;
use App\Services\Trust\TrustService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Business rules for the lending ledger. Controllers stay thin and call these methods.
 */
class LoanService
{
    public function __construct(
        private readonly LoanStateMachine $stateMachine,
        private readonly TrustService $trust,
    ) {}

    /**
     * A student asks for a loan. The amount is capped by their trust tier.
     *
     * @param  array{amount: int|float|string, purpose?: string|null, due_date: string}  $data
     *
     * @throws ValidationException
     */
    public function request(User $borrower, array $data): Loan
    {
        $maxLoan = $this->trust->current($borrower)->maxLoan;
        $limit = min($maxLoan, config('lending.max_amount'));

        if ($limit <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Your trust score is too low to borrow right now.',
            ]);
        }

        if ((float) $data['amount'] > $limit) {
            throw ValidationException::withMessages([
                'amount' => "Your trust tier allows loans up to \${$limit}.",
            ]);
        }

        $hasLateLoan = Loan::query()
            ->where('borrower_id', $borrower->id)
            ->whereIn('status', [LoanStatus::Overdue, LoanStatus::Defaulted])
            ->exists();

        if ($hasLateLoan) {
            throw ValidationException::withMessages([
                'amount' => 'Repay your overdue loan before borrowing again.',
            ]);
        }

        $openLoans = Loan::query()
            ->where('borrower_id', $borrower->id)
            ->whereIn('status', LoanStatus::open())
            ->count();

        if ($openLoans >= config('lending.max_open_loans')) {
            throw ValidationException::withMessages([
                'amount' => 'You already have the maximum number of open loans.',
            ]);
        }

        $loan = new Loan($data);
        $loan->borrower()->associate($borrower);
        $loan->status = LoanStatus::Requested;
        $loan->save();

        return $loan;
    }

    /**
     * A lender takes up an open request. Locked so two lenders cannot both win.
     */
    public function accept(Loan $loan, User $lender): Loan
    {
        return DB::transaction(function () use ($loan, $lender): Loan {
            $locked = Loan::whereKey($loan->id)->lockForUpdate()->firstOrFail();

            $this->stateMachine->transition($locked, LoanStatus::Accepted);
            $locked->lender()->associate($lender);
            $locked->save();

            return $locked;
        });
    }

    /**
     * One side confirms the cash changed hands; the loan goes live once both have.
     */
    public function confirmHandover(Loan $loan, User $actor): Loan
    {
        return DB::transaction(function () use ($loan, $actor): Loan {
            $locked = Loan::whereKey($loan->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== LoanStatus::Accepted) {
                throw new InvalidLoanTransition($locked->status, LoanStatus::Active);
            }

            $locked->{$locked->roleOf($actor).'_handover_at'} ??= now();

            if ($locked->lender_handover_at && $locked->borrower_handover_at) {
                $this->stateMachine->transition($locked, LoanStatus::Active);
                $locked->activated_at = now();
            }

            $locked->save();

            return $locked;
        });
    }

    /**
     * One side confirms the money came back; the loan closes once both have.
     */
    public function confirmRepayment(Loan $loan, User $actor): Loan
    {
        return DB::transaction(function () use ($loan, $actor): Loan {
            $locked = Loan::whereKey($loan->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, LoanStatus::owed(), true)) {
                throw new InvalidLoanTransition($locked->status, LoanStatus::Repaid);
            }

            $locked->{$locked->roleOf($actor).'_repayment_at'} ??= now();

            if ($locked->lender_repayment_at && $locked->borrower_repayment_at) {
                $wasOnTime = $locked->status === LoanStatus::Active;

                $this->stateMachine->transition($locked, LoanStatus::Repaid);
                $locked->repaid_at = now();
                $locked->save();

                $this->trust->record(
                    $locked->borrower,
                    $wasOnTime ? TrustEventType::LoanRepaidOnTime : TrustEventType::LoanRepaidLate,
                    $locked,
                );

                return $locked;
            }

            $locked->save();

            return $locked;
        });
    }

    /**
     * Back out before the money moves. Borrowers may withdraw a request; lenders may drop an accepted one.
     */
    public function cancel(Loan $loan, User $actor): Loan
    {
        return DB::transaction(function () use ($loan): Loan {
            $locked = Loan::whereKey($loan->id)->lockForUpdate()->firstOrFail();

            $this->stateMachine->transition($locked, LoanStatus::Cancelled);
            $locked->save();

            return $locked;
        });
    }

    /**
     * Scheduler step: an unpaid loan past its due date becomes overdue and costs trust.
     */
    public function markOverdue(Loan $loan): void
    {
        $this->stateMachine->transition($loan, LoanStatus::Overdue);
        $loan->save();

        $this->trust->record($loan->borrower, TrustEventType::LoanOverdue, $loan);
    }

    /**
     * Scheduler step: a loan overdue for too long counts as defaulted.
     */
    public function markDefaulted(Loan $loan): void
    {
        $this->stateMachine->transition($loan, LoanStatus::Defaulted);
        $loan->save();

        $this->trust->record($loan->borrower, TrustEventType::LoanDefaulted, $loan);
    }
}
