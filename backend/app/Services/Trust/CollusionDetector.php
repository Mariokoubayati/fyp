<?php

namespace App\Services\Trust;

use App\Enums\LoanStatus;
use App\Models\Loan;
use App\Models\User;
use App\Models\Vouch;
use Carbon\CarbonInterface;

/**
 * Looks at a student's circle (vouches and lending partners) for signs of collusion.
 */
class CollusionDetector
{
    public function signalsFor(User $user, CarbonInterface $now): CollusionSignals
    {
        $voucherIds = Vouch::where('vouchee_id', $user->id)->pluck('voucher_id');

        $mutualVoucherIds = Vouch::where('voucher_id', $user->id)
            ->whereIn('vouchee_id', $voucherIds)
            ->pluck('vouchee_id');

        $ineligibleVoucherIds = User::query()
            ->whereIn('id', $voucherIds)
            ->where(fn ($query) => $query
                ->whereNull('email_verified_at')
                ->orWhere('trust_score', '<', config('trust.anti_collusion.min_voucher_score')))
            ->pluck('id');

        return new CollusionSignals(
            mutualVoucherIds: $mutualVoucherIds->map(fn ($id): int => (int) $id)->values()->all(),
            ineligibleVoucherIds: $ineligibleVoucherIds->map(fn ($id): int => (int) $id)->values()->all(),
            circularLending: $this->hasCircularLending($user, $now),
        );
    }

    /**
     * True when money (on paper) went around in a circle through this student:
     * they lent to someone who also lent to them, or A->B->C->A.
     */
    private function hasCircularLending(User $user, CarbonInterface $now): bool
    {
        $since = $now->copy()->subDays(config('trust.anti_collusion.circular_window_days'));

        $handedOver = [LoanStatus::Active, LoanStatus::Repaid, LoanStatus::Overdue, LoanStatus::Defaulted];

        // Every handed-over loan in the window touching the student or their direct partners.
        $direct = Loan::query()
            ->whereIn('status', $handedOver)
            ->where('activated_at', '>=', $since)
            ->where(fn ($query) => $query->where('lender_id', $user->id)->orWhere('borrower_id', $user->id))
            ->get(['lender_id', 'borrower_id']);

        $lentTo = $direct->where('lender_id', $user->id)->pluck('borrower_id')->unique();
        $borrowedFrom = $direct->where('borrower_id', $user->id)->pluck('lender_id')->unique();

        // A -> B and B -> A.
        if ($lentTo->intersect($borrowedFrom)->isNotEmpty()) {
            return true;
        }

        if ($lentTo->isEmpty() || $borrowedFrom->isEmpty()) {
            return false;
        }

        // A -> B -> C -> A: someone the student lent to lent to someone who lent to the student.
        return Loan::query()
            ->whereIn('status', $handedOver)
            ->where('activated_at', '>=', $since)
            ->whereIn('lender_id', $lentTo)
            ->whereIn('borrower_id', $borrowedFrom)
            ->exists();
    }
}
