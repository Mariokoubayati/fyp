<?php

namespace App\Services\Trust;

use App\Models\TrustEvent;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Pure, rule-based trust score: baseline plus capped component points, clamped to the allowed range.
 */
class TrustScoreCalculator
{
    /**
     * @param  Collection<int, TrustEvent>  $events  Every trust event of the student.
     */
    public function calculate(User $user, Collection $events, CarbonInterface $now): TrustScore
    {
        $breakdown = [
            'repayment' => $this->capped('repayment', $this->eventPoints('repayment', $events)),
            'marketplace' => $this->capped('marketplace', $this->marketplacePoints($events)),
            'vouches' => $this->capped('vouches', $this->eventPoints('vouches', $events)),
            'account' => $this->capped('account', $this->accountPoints($user, $now)),
            'engagement' => $this->capped('engagement', $this->eventPoints('engagement', $events)),
        ];

        $score = (int) max(
            config('trust.min'),
            min(config('trust.max'), config('trust.baseline') + array_sum($breakdown)),
        );

        $tier = $this->tierFor($score);

        return new TrustScore($score, $tier['name'], $tier['max_loan'], $breakdown);
    }

    /**
     * @return array{name: string, min_score: int, max_loan: int}
     */
    public function tierFor(int $score): array
    {
        $tiers = collect(config('trust.tiers'))->sortByDesc('min_score');

        return $tiers->first(fn (array $tier): bool => $score >= $tier['min_score']) ?? $tiers->last();
    }

    /**
     * @param  Collection<int, TrustEvent>  $events
     */
    private function eventPoints(string $component, Collection $events): int
    {
        $points = config("trust.components.{$component}.events");

        return (int) $events->sum(fn (TrustEvent $event): int => $points[$event->type->value] ?? 0);
    }

    /**
     * Sales and purchases count a flat amount; ratings scale with the number of stars.
     *
     * @param  Collection<int, TrustEvent>  $events
     */
    private function marketplacePoints(Collection $events): int
    {
        $rating = config('trust.components.marketplace.rating');

        $ratingPoints = $events
            ->filter(fn (TrustEvent $event): bool => $event->type->value === 'rating_received' && $event->value !== null)
            ->sum(fn (TrustEvent $event): int => ($event->value - $rating['neutral_stars']) * $rating['points_per_star']);

        return $this->eventPoints('marketplace', $events) + (int) $ratingPoints;
    }

    private function accountPoints(User $user, CarbonInterface $now): int
    {
        $account = config('trust.components.account');

        $verification = $user->hasVerifiedEmail() ? $account['verified_email'] : $account['unverified_email'];
        $days = max(0, (int) $user->created_at->diffInDays($now));
        $age = min($account['age_cap'], intdiv($days, 30) * $account['points_per_30_days']);

        return $verification + $age;
    }

    private function capped(string $component, int $points): int
    {
        $cap = config("trust.components.{$component}.cap");

        return max(-$cap, min($cap, $points));
    }
}
