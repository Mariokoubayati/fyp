<?php

namespace App\Policies;

use App\Enums\LoanStatus;
use App\Models\Loan;
use App\Models\User;

class LoanPolicy
{
    /**
     * Participants see their loan; any student can see an open request to decide whether to fund it.
     */
    public function view(User $user, Loan $loan): bool
    {
        return $loan->roleOf($user) !== null || $loan->status === LoanStatus::Requested;
    }

    /**
     * Anyone but the borrower may fund a request, as long as their email is verified.
     */
    public function accept(User $user, Loan $loan): bool
    {
        return $user->hasVerifiedEmail() && $loan->borrower_id !== $user->id;
    }

    /**
     * Confirming handover or repayment is for the two parties only.
     */
    public function confirm(User $user, Loan $loan): bool
    {
        return $loan->roleOf($user) !== null;
    }

    /**
     * Borrowers may cancel a request or accepted loan; the lender may drop an accepted one.
     */
    public function cancel(User $user, Loan $loan): bool
    {
        return match ($loan->roleOf($user)) {
            'borrower' => in_array($loan->status, [LoanStatus::Requested, LoanStatus::Accepted], true),
            'lender' => $loan->status === LoanStatus::Accepted,
            default => false,
        };
    }
}
