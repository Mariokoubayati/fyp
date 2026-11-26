<?php

namespace App\Services\Badges;

use App\Enums\Badge;
use App\Enums\ProjectMemberStatus;
use App\Enums\TrustEventType;
use App\Models\ListingRating;
use App\Models\TrustEvent;
use App\Models\User;
use App\Models\UserBadge;
use App\Notifications\BadgeEarnedNotification;
use Illuminate\Support\Facades\DB;

/**
 * Works out each student's progress towards every badge and awards the ones they reached.
 *
 * Marketplace, lending and vouch counts come from trust events, so a loan the anti-collusion
 * rules discounted does not count here either. Badges never feed back into the trust score.
 */
class BadgeService
{
    private static bool $quiet = false;

    /**
     * Run a block without sending "You earned a badge" notifications (used by the demo seeder).
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function quietly(callable $callback): mixed
    {
        $previous = self::$quiet;
        self::$quiet = true;

        try {
            return $callback();
        } finally {
            self::$quiet = $previous;
        }
    }

    /**
     * Award every badge the student has reached and does not have yet.
     *
     * @return list<Badge> the badges awarded now
     */
    public function check(User $user): array
    {
        $earned = $user->badges()->pluck('badge')->map(fn (Badge $badge): string => $badge->value)->all();
        $missing = array_filter(Badge::cases(), fn (Badge $badge): bool => ! in_array($badge->value, $earned, true));

        if ($missing === []) {
            return [];
        }

        $progress = $this->progress($user, $missing);
        $awarded = [];

        foreach ($missing as $badge) {
            if (! $progress[$badge->value]['met']) {
                continue;
            }

            $created = UserBadge::firstOrCreate(
                ['user_id' => $user->id, 'badge' => $badge],
                ['earned_at' => now()],
            );

            if ($created->wasRecentlyCreated) {
                $awarded[] = $badge;

                if (! self::$quiet) {
                    $user->notify(new BadgeEarnedNotification($badge));
                }
            }
        }

        return $awarded;
    }

    /**
     * Progress towards each badge: the count so far, and whether the badge's rule is met.
     *
     * @param  list<Badge>|null  $badges  only these (all when null)
     * @return array<string, array{value: int, goal: int, met: bool}>
     */
    public function progress(User $user, ?array $badges = null): array
    {
        $result = [];

        foreach ($badges ?? Badge::cases() as $badge) {
            $value = $this->measure($user, $badge);
            $met = $value >= $badge->goal();

            if ($badge === Badge::TopSeller && $met) {
                $met = (float) ListingRating::where('ratee_id', $user->id)->avg('stars') >= 4.5;
            }

            $result[$badge->value] = ['value' => min($value, $badge->goal()), 'goal' => $badge->goal(), 'met' => $met];
        }

        return $result;
    }

    private function measure(User $user, Badge $badge): int
    {
        return match ($badge) {
            Badge::FirstSale => $this->events($user, TrustEventType::SaleCompleted, counted: false),
            Badge::TopSeller => $this->events($user, TrustEventType::SaleCompleted),
            Badge::ReliableBorrower => $this->events($user, TrustEventType::LoanRepaidOnTime),
            Badge::StudyBuddy => DB::table('study_group_user')->where('user_id', $user->id)->count(),
            Badge::WellVouched => $this->events($user, TrustEventType::VouchReceived),
            Badge::HelpingHand => TrustEvent::query()
                ->where('counterparty_id', $user->id)
                ->whereIn('type', [TrustEventType::LoanRepaidOnTime, TrustEventType::LoanRepaidLate])
                ->whereNull('discount_reason')
                ->distinct()
                ->count('user_id'),
            Badge::EventGoer => DB::table('event_user')
                ->join('events', 'events.id', '=', 'event_user.event_id')
                ->where('event_user.user_id', $user->id)
                ->where('events.creator_id', '!=', $user->id)
                ->where('events.starts_at', '<=', now())
                ->count(),
            Badge::EventHost => (int) DB::table('events')
                ->join('event_user', 'event_user.event_id', '=', 'events.id')
                ->where('events.creator_id', $user->id)
                ->where('event_user.user_id', '!=', $user->id)
                ->groupBy('events.id')
                ->selectRaw('count(*) as going')
                ->get()
                ->max('going'),
            Badge::TeamPlayer => DB::table('project_user')
                ->join('projects', 'projects.id', '=', 'project_user.project_id')
                ->where('project_user.user_id', $user->id)
                ->where('project_user.status', ProjectMemberStatus::Accepted->value)
                ->where('projects.owner_id', '!=', $user->id)
                ->count(),
        };
    }

    /**
     * Trust events of one type. "Counted" leaves out the ones the anti-collusion rules discounted.
     */
    private function events(User $user, TrustEventType $type, bool $counted = true): int
    {
        return $user->trustEvents()
            ->where('type', $type)
            ->when($counted, fn ($query) => $query->whereNull('discount_reason'))
            ->count();
    }
}
