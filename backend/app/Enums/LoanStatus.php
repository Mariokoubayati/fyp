<?php

namespace App\Enums;

enum LoanStatus: string
{
    case Requested = 'requested';
    case Accepted = 'accepted';
    case Active = 'active';
    case Repaid = 'repaid';
    case Overdue = 'overdue';
    case Defaulted = 'defaulted';
    case Cancelled = 'cancelled';

    /**
     * Loans that still count against the borrower's open-loan limit.
     *
     * @return array<int, self>
     */
    public static function open(): array
    {
        return [self::Requested, self::Accepted, self::Active, self::Overdue, self::Defaulted];
    }

    /**
     * Money that was handed over and is still owed.
     *
     * @return array<int, self>
     */
    public static function owed(): array
    {
        return [self::Active, self::Overdue, self::Defaulted];
    }
}
