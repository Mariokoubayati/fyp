<?php

namespace App\Services\Trust;

use App\Enums\ListingStatus;
use App\Enums\LoanStatus;
use App\Models\Listing;
use App\Models\ListingRating;
use App\Models\Loan;
use App\Models\User;
use App\Models\Vouch;
use Carbon\CarbonInterface;

/**
 * Builds a student's history in the shared format the AI service reads (ml/unicircle_ml/history.py).
 * The Python side computes the features, so the two never drift apart.
 */
class TrustHistoryBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function build(User $user, CarbonInterface $now, CollusionSignals $signals): array
    {
        $handedOver = [LoanStatus::Active, LoanStatus::Repaid, LoanStatus::Overdue, LoanStatus::Defaulted];

        $borrowed = Loan::query()
            ->where('borrower_id', $user->id)
            ->whereIn('status', $handedOver)
            ->whereNotNull('activated_at')
            ->get()
            ->map(fn (Loan $loan): array => [
                'lender_id' => $loan->lender_id,
                'amount' => (float) $loan->amount,
                'activated_at' => $loan->activated_at->getTimestamp(),
                // Overdue starts once the due date has fully passed.
                'due_at' => $loan->due_date->copy()->endOfDay()->getTimestamp(),
                'repaid_at' => $loan->repaid_at?->getTimestamp(),
            ])
            ->values()
            ->all();

        $lent = Loan::query()
            ->where('lender_id', $user->id)
            ->whereIn('status', $handedOver)
            ->whereNotNull('activated_at')
            ->get(['borrower_id', 'activated_at'])
            ->map(fn (Loan $loan): array => [
                'borrower_id' => $loan->borrower_id,
                'activated_at' => $loan->activated_at->getTimestamp(),
            ])
            ->values()
            ->all();

        $vouches = Vouch::where('vouchee_id', $user->id)
            ->pluck('voucher_id')
            ->map(fn ($voucherId): array => [
                'voucher_id' => (int) $voucherId,
                'mutual' => in_array((int) $voucherId, $signals->mutualVoucherIds, true),
                'eligible' => ! in_array((int) $voucherId, $signals->ineligibleVoucherIds, true),
            ])
            ->values()
            ->all();

        $sold = Listing::query()
            ->where('seller_id', $user->id)
            ->where('status', ListingStatus::Sold)
            ->whereNotNull('buyer_id')
            ->get(['id', 'buyer_id', 'sold_at']);

        $stars = ListingRating::whereIn('listing_id', $sold->pluck('id'))->pluck('stars', 'listing_id');

        $sales = $sold
            ->map(fn (Listing $listing): array => [
                'buyer_id' => $listing->buyer_id,
                'stars' => $stars[$listing->id] ?? null,
                'sold_at' => ($listing->sold_at ?? $now)->getTimestamp(),
            ])
            ->values()
            ->all();

        return [
            // Rounded to the hour so repeated calls within an hour can share a cached answer.
            'now' => $now->copy()->startOfHour()->getTimestamp(),
            'account_created_at' => $user->created_at->getTimestamp(),
            'email_verified' => $user->hasVerifiedEmail(),
            'borrowed' => $borrowed,
            'lent' => $lent,
            'vouches' => $vouches,
            'sales' => $sales,
            'circular_lending' => $signals->circularLending,
        ];
    }
}
