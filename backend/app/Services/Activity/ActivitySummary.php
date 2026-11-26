<?php

namespace App\Services\Activity;

use App\Enums\Badge;
use App\Enums\ListingStatus;
use App\Enums\LoanStatus;
use App\Enums\ProjectMemberStatus;
use App\Enums\TrustEventType;
use App\Models\Listing;
use App\Models\Loan;
use App\Models\User;
use App\Services\Badges\BadgeService;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The numbers behind a student's "My activity" screen: marketplace, lending, groups,
 * events and projects, the last four months (this semester), and progress towards every badge.
 *
 * Read-only queries over the other features' tables. Months are grouped in PHP so the
 * same code works on MySQL and SQLite.
 */
class ActivitySummary
{
    public const MONTHS = 4;

    public function __construct(private readonly BadgeService $badges) {}

    /**
     * @return array<string, mixed>
     */
    public function for(User $user): array
    {
        $since = now()->startOfMonth()->subMonths(self::MONTHS - 1);
        $sold = Listing::where('seller_id', $user->id)->where('status', ListingStatus::Sold)->get(['price', 'sold_at']);
        $bought = Listing::where('buyer_id', $user->id)->where('status', ListingStatus::Sold)->get(['price', 'sold_at']);
        $handedOver = [LoanStatus::Active, LoanStatus::Repaid, LoanStatus::Overdue, LoanStatus::Defaulted];
        $borrowed = Loan::where('borrower_id', $user->id)->whereIn('status', $handedOver)->get(['amount', 'activated_at']);
        $lent = Loan::where('lender_id', $user->id)->whereIn('status', $handedOver)->get(['amount', 'activated_at']);
        $groupJoins = DB::table('study_group_user')->where('user_id', $user->id)->pluck('created_at');
        $eventsGone = DB::table('event_user')
            ->join('events', 'events.id', '=', 'event_user.event_id')
            ->where('event_user.user_id', $user->id)
            ->where('events.starts_at', '<=', now())
            ->pluck('events.starts_at');

        return [
            'marketplace' => [
                'sold' => $sold->count(),
                'earned' => round((float) $sold->sum('price'), 2),
                'bought' => $bought->count(),
                'spent' => round((float) $bought->sum('price'), 2),
                'for_sale' => Listing::where('seller_id', $user->id)->where('status', ListingStatus::Available)->count(),
            ],
            'lending' => [
                'borrowed' => $borrowed->count(),
                'borrowed_amount' => round((float) $borrowed->sum('amount'), 2),
                'lent' => $lent->count(),
                'lent_amount' => round((float) $lent->sum('amount'), 2),
                'repaid_on_time' => $user->trustEvents()->where('type', TrustEventType::LoanRepaidOnTime)->count(),
                'repaid_late' => $user->trustEvents()->where('type', TrustEventType::LoanRepaidLate)->count(),
                'open' => Loan::query()
                    ->where(fn ($query) => $query->where('borrower_id', $user->id)->orWhere('lender_id', $user->id))
                    ->whereIn('status', [LoanStatus::Requested, LoanStatus::Accepted, LoanStatus::Active, LoanStatus::Overdue])
                    ->count(),
            ],
            'groups' => [
                'joined' => $groupJoins->count(),
                'started' => DB::table('study_groups')->where('creator_id', $user->id)->count(),
                'upcoming' => DB::table('study_group_user')
                    ->join('study_groups', 'study_groups.id', '=', 'study_group_user.study_group_id')
                    ->where('study_group_user.user_id', $user->id)
                    ->where('study_groups.starts_at', '>', now())
                    ->count(),
            ],
            'events' => [
                'went' => $eventsGone->count(),
                'upcoming' => DB::table('event_user')
                    ->join('events', 'events.id', '=', 'event_user.event_id')
                    ->where('event_user.user_id', $user->id)
                    ->where('events.starts_at', '>', now())
                    ->count(),
                'hosted' => DB::table('events')->where('creator_id', $user->id)->count(),
            ],
            'projects' => [
                'owned' => DB::table('projects')->where('owner_id', $user->id)->count(),
                'joined' => DB::table('project_user')
                    ->join('projects', 'projects.id', '=', 'project_user.project_id')
                    ->where('project_user.user_id', $user->id)
                    ->where('project_user.status', ProjectMemberStatus::Accepted->value)
                    ->where('projects.owner_id', '!=', $user->id)
                    ->count(),
            ],
            'months' => $this->months($since, $sold, $bought, $borrowed->concat($lent), $groupJoins, $eventsGone),
            'badges' => $this->badgeList($user),
        ];
    }

    /**
     * Every badge, earned or not, with progress.
     *
     * @return list<array<string, mixed>>
     */
    public function badgeList(User $user): array
    {
        $earned = $user->badges()->get()->keyBy(fn ($row) => $row->badge->value);
        $progress = $this->badges->progress($user);

        return array_map(fn (Badge $badge): array => [
            'key' => $badge->value,
            'title' => $badge->title(),
            'description' => $badge->description(),
            'icon' => $badge->icon(),
            'progress' => $earned->has($badge->value) ? $badge->goal() : $progress[$badge->value]['value'],
            'goal' => $badge->goal(),
            'earned_at' => $earned->get($badge->value)?->earned_at,
        ], Badge::cases());
    }

    /**
     * @param  Collection<int, Listing>  $sold
     * @param  Collection<int, Listing>  $bought
     * @param  Collection<int, Loan>  $loans
     * @param  Collection<int, mixed>  $groupJoins
     * @param  Collection<int, mixed>  $eventsGone
     * @return list<array<string, mixed>>
     */
    private function months(CarbonInterface $since, Collection $sold, Collection $bought, Collection $loans, Collection $groupJoins, Collection $eventsGone): array
    {
        $key = fn (mixed $date): ?string => $date === null ? null : Carbon::parse($date)->format('Y-m');
        $inMonth = fn (Collection $dates, string $month): int => $dates->filter(fn ($date) => $key($date) === $month)->count();

        $months = [];

        for ($i = 0; $i < self::MONTHS; $i++) {
            $start = $since->copy()->addMonths($i);
            $month = $start->format('Y-m');
            $soldThisMonth = $sold->filter(fn (Listing $listing) => $key($listing->sold_at) === $month);
            $boughtThisMonth = $bought->filter(fn (Listing $listing) => $key($listing->sold_at) === $month);

            $months[] = [
                'month' => $month,
                'label' => $start->format('M'),
                'earned' => round((float) $soldThisMonth->sum('price'), 2),
                'spent' => round((float) $boughtThisMonth->sum('price'), 2),
                'marketplace' => $soldThisMonth->count() + $boughtThisMonth->count(),
                'loans' => $inMonth($loans->pluck('activated_at'), $month),
                'groups' => $inMonth($groupJoins, $month),
                'events' => $inMonth($eventsGone, $month),
            ];
        }

        return $months;
    }
}
