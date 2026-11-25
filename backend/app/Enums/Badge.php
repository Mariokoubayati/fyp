<?php

namespace App\Enums;

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
