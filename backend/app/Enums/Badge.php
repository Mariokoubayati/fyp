<?php

namespace App\Enums;

use App\Models\User;
use App\Models\UserBadge;

/**
 * Badges reward taking part. They are shown on profiles but never change the trust score.
 */
enum Badge: string
{
    case FirstSale = 'first_sale';
    case TopSeller = 'top_seller';
    case ReliableBorrower = 'reliable_borrower';
    case HelpingHand = 'helping_hand';
    case StudyBuddy = 'study_buddy';
    case EventGoer = 'event_goer';
    case EventHost = 'event_host';
    case TeamPlayer = 'team_player';
    case WellVouched = 'well_vouched';

    public function title(): string
    {
        return match ($this) {
            self::FirstSale => 'First sale',
            self::TopSeller => 'Top seller',
            self::ReliableBorrower => 'Reliable borrower',
            self::HelpingHand => 'Helping hand',
            self::StudyBuddy => 'Study buddy',
            self::EventGoer => 'Event goer',
            self::EventHost => 'Event host',
            self::TeamPlayer => 'Team player',
            self::WellVouched => 'Vouched for',
        };
    }

    /**
     * How to earn it, short enough for a badge tile.
     */
    public function description(): string
    {
        return match ($this) {
            self::FirstSale => 'Sell an item on the marketplace.',
            self::TopSeller => '5 sales, rated 4.5 stars or more.',
            self::ReliableBorrower => 'Pay back 3 loans on time.',
            self::HelpingHand => 'Lend to 3 students who pay you back.',
            self::StudyBuddy => 'Join 3 study groups.',
            self::EventGoer => 'Go to 3 campus events.',
            self::EventHost => 'Host an event 5 students go to.',
            self::TeamPlayer => 'Join a student project team.',
            self::WellVouched => 'Get vouches from 3 students.',
        };
    }

    /**
     * Ionicons name the app shows for the badge.
     */
    public function icon(): string
    {
        return match ($this) {
            self::FirstSale => 'pricetag',
            self::TopSeller => 'star',
            self::ReliableBorrower => 'shield-checkmark',
            self::HelpingHand => 'hand-left',
            self::StudyBuddy => 'book',
            self::EventGoer => 'calendar',
            self::EventHost => 'megaphone',
            self::TeamPlayer => 'people',
            self::WellVouched => 'thumbs-up',
        };
    }

    /**
     * The badges that matter where a student decides about someone, strongest first:
     * a buyer looking at a seller, a lender at a borrower, an owner at a join request.
     *
     * @param  'seller'|'borrower'|'lender'|'teammate'  $role
     * @return list<self>
     */
    public static function relevantTo(string $role): array
    {
        return match ($role) {
            'seller' => [self::TopSeller, self::FirstSale, self::WellVouched],
            'borrower' => [self::ReliableBorrower, self::WellVouched],
            'lender' => [self::HelpingHand, self::WellVouched],
            'teammate' => [self::TeamPlayer, self::StudyBuddy, self::EventHost],
        };
    }

    /**
     * The student's earned badges that matter for this role, ready for the API.
     * Uses the loaded "badges" relation (eager-load "seller.badges" in lists to avoid a query per row).
     *
     * @param  'seller'|'borrower'|'lender'|'teammate'  $role
     * @return list<array{key: string, title: string, icon: string}>
     */
    public static function shownFor(User $user, string $role): array
    {
        $earned = $user->loadMissing('badges')->badges->map(fn (UserBadge $row): self => $row->badge);
        $shown = array_values(array_filter(self::relevantTo($role), fn (self $badge): bool => $earned->contains($badge)));

        // "Top seller" already says more than "First sale".
        if (in_array(self::TopSeller, $shown, true)) {
            $shown = array_values(array_filter($shown, fn (self $badge): bool => $badge !== self::FirstSale));
        }

        return array_map(fn (self $badge): array => ['key' => $badge->value, 'title' => $badge->title(), 'icon' => $badge->icon()], $shown);
    }

    /**
     * How many of the counted thing the badge needs.
     */
    public function goal(): int
    {
        return match ($this) {
            self::FirstSale, self::TeamPlayer => 1,
            self::TopSeller, self::EventHost => 5,
            default => 3,
        };
    }
}
