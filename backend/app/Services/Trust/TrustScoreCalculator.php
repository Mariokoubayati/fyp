<?php

namespace App\Services\Trust;

use App\Models\TrustEvent;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Pure, rule-based trust score: baseline plus capped component points, clamped to the allowed range,
 * with anti-collusion rules that limit what one partner or a closed circle can add.
 */
class TrustScoreCalculator
{
    public const FLAG_UNVERIFIED = 'unverified_email';

    public const FLAG_NEW_ACCOUNT = 'new_account';

    public const FLAG_CIRCULAR_LENDING = 'circular_lending';

    /**
     * @param  Collection<int, TrustEvent>  $events  Every trust event of the student.
     */
    public function calculate(User $user, Collection $events, CarbonInterface $now, ?CollusionSignals $signals = null): TrustScore
    {
        $signals ??= CollusionSignals::none();

        $counted = $this->withPairCap(
            $events->filter(fn (TrustEvent $event): bool => $event->discount_reason === null)
        );

        $breakdown = [
            'repayment' => $this->capped('repayment', $this->repaymentPoints($counted)),
            'marketplace' => $this->capped('marketplace', $this->marketplacePoints($counted)),
            'vouches' => $this->capped('vouches', $this->vouchPoints($counted, $signals)),
        ];

        $score = (int) max(
            config('trust.min'),
            min(config('trust.max'), config('trust.baseline') + array_sum($breakdown)),
        );

        $flags = $this->flags($user, $now, $signals);
        $tier = $this->tierFor($score, $flags);

        return new TrustScore($score, $tier['name'], $tier['max_loan'], $breakdown, $flags);
    }

    /**
     * The tier for a score. Flags lower the ceiling: unverified students cannot borrow,
     * and new accounts or circular lenders cannot go above the basic tier.
     *
     * @param  list<string>  $flags
     * @return array{name: string, min_score: int, max_loan: int}
     */
    public function tierFor(int $score, array $flags = []): array
    {
        $tiers = collect(config('trust.tiers'))->sortByDesc('min_score')->values();

        if (in_array(self::FLAG_UNVERIFIED, $flags, true)) {
            return $tiers->last();
        }

        $tier = $tiers->first(fn (array $tier): bool => $score >= $tier['min_score']) ?? $tiers->last();

        $capsAtSecondTier = array_intersect($flags, [self::FLAG_NEW_ACCOUNT, self::FLAG_CIRCULAR_LENDING]) !== [];

        if ($capsAtSecondTier && $tier === $tiers->first()) {
            return $tiers->get(1);
        }

        return $tier;
    }

    /**
     * @return list<string>
     */
    private function flags(User $user, CarbonInterface $now, CollusionSignals $signals): array
    {
        $flags = [];

        if (! $user->hasVerifiedEmail()) {
            $flags[] = self::FLAG_UNVERIFIED;
        }

        $accountDays = (int) $user->created_at->diffInDays($now);

        if ($accountDays < config('trust.anti_collusion.min_account_days_for_top_tier')) {
            $flags[] = self::FLAG_NEW_ACCOUNT;
        }

        if ($signals->circularLending) {
            $flags[] = self::FLAG_CIRCULAR_LENDING;
        }

        return $flags;
    }

    /**
     * Keep every penalty, but only the first few rewards from the same counterparty
     * per component inside the rolling window.
     *
     * @param  Collection<int, TrustEvent>  $events
     * @return Collection<int, TrustEvent>
     */
    private function withPairCap(Collection $events): Collection
    {
        $max = config('trust.anti_collusion.max_rewards_per_pair');
        $windowDays = config('trust.anti_collusion.pair_window_days');
        $accepted = [];

        return $events
            ->sortBy(fn (TrustEvent $event) => $event->occurred_at?->getTimestamp() ?? 0)
            ->filter(function (TrustEvent $event) use (&$accepted, $max, $windowDays): bool {
                if ($event->counterparty_id === null || ! $this->isReward($event)) {
                    return true;
                }

                $key = $this->componentOf($event).':'.$event->counterparty_id;
                $at = $event->occurred_at;

                $recent = array_filter(
                    $accepted[$key] ?? [],
                    fn (CarbonInterface $earlier): bool => $at === null || $earlier->diffInDays($at) < $windowDays,
                );

                if (count($recent) >= $max) {
                    return false;
                }

                $accepted[$key][] = $at ?? now();

                return true;
            })
            ->values();
    }

    /**
     * Rewards scale with how many different lenders the student repaid; penalties always count in full.
     *
     * @param  Collection<int, TrustEvent>  $events
     */
    private function repaymentPoints(Collection $events): int
    {
        $points = config('trust.components.repayment.events');
        $repayment = $events->filter(fn (TrustEvent $event): bool => array_key_exists($event->type->value, $points));

        [$rewards, $penalties] = $repayment->partition(fn (TrustEvent $event): bool => $points[$event->type->value] > 0);

        $rewardPoints = $rewards->sum(fn (TrustEvent $event): int => $points[$event->type->value]);
        $penaltyPoints = $penalties->sum(fn (TrustEvent $event): int => $points[$event->type->value]);

        return (int) round($rewardPoints * $this->lenderDiversity($rewards)) + (int) $penaltyPoints;
    }

    /**
     * 1 when the rewards come from enough different lenders, less when they all come from one or two.
     * Events without a recorded lender (older data) count as distinct.
     *
     * @param  Collection<int, TrustEvent>  $rewards
     */
    private function lenderDiversity(Collection $rewards): float
    {
        if ($rewards->isEmpty()) {
            return 1.0;
        }

        $known = $rewards->whereNotNull('counterparty_id');
        $distinct = $known->pluck('counterparty_id')->unique()->count() + ($rewards->count() - $known->count());

        return min(1.0, $distinct / config('trust.anti_collusion.full_weight_lenders'));
    }

    /**
     * Sales and purchases count a flat amount; ratings scale with the number of stars.
     *
     * @param  Collection<int, TrustEvent>  $events
     */
    private function marketplacePoints(Collection $events): int
    {
        $points = config('trust.components.marketplace.events');
        $rating = config('trust.components.marketplace.rating');

        $flat = $events->sum(fn (TrustEvent $event): int => $points[$event->type->value] ?? 0);

        $ratingPoints = $events
            ->filter(fn (TrustEvent $event): bool => $event->type->value === 'rating_received' && $event->value !== null)
            ->sum(fn (TrustEvent $event): int => ($event->value - $rating['neutral_stars']) * $rating['points_per_star']);

        return (int) $flat + (int) $ratingPoints;
    }

    /**
     * Vouches from ineligible students count nothing; vouches returned in both directions count less.
     *
     * @param  Collection<int, TrustEvent>  $events
     */
    private function vouchPoints(Collection $events, CollusionSignals $signals): int
    {
        $perVouch = config('trust.components.vouches.events.vouch_received');
        $mutualWeight = config('trust.anti_collusion.mutual_vouch_weight');

        return (int) round($events
            ->filter(fn (TrustEvent $event): bool => $event->type->value === 'vouch_received')
            ->sum(function (TrustEvent $event) use ($signals, $perVouch, $mutualWeight): float {
                $voucherId = (int) ($event->counterparty_id ?? $event->subject_id);

                if (in_array($voucherId, $signals->ineligibleVoucherIds, true)) {
                    return 0;
                }

                return in_array($voucherId, $signals->mutualVoucherIds, true) ? $perVouch * $mutualWeight : $perVouch;
            }));
    }

    private function isReward(TrustEvent $event): bool
    {
        if ($event->type->value === 'rating_received') {
            return ($event->value ?? 0) > config('trust.components.marketplace.rating.neutral_stars');
        }

        $points = config('trust.components.'.$this->componentOf($event).'.events', []);

        return ($points[$event->type->value] ?? 0) > 0;
    }

    private function componentOf(TrustEvent $event): string
    {
        foreach (config('trust.components') as $name => $component) {
            if (array_key_exists($event->type->value, $component['events'] ?? [])) {
                return $name;
            }
        }

        return $event->type->value === 'rating_received' ? 'marketplace' : 'other';
    }

    private function capped(string $component, int $points): int
    {
        $cap = config("trust.components.{$component}.cap");

        return max(-$cap, min($cap, $points));
    }
}
