<?php

namespace App\Services\Trust;

/**
 * Facts about a student's circle that the calculator cannot see from their own events.
 */
final readonly class CollusionSignals
{
    /**
     * @param  list<int>  $mutualVoucherIds  Students who vouched for this student and were vouched for back.
     * @param  list<int>  $ineligibleVoucherIds  Vouchers who are unverified or below the minimum score.
     * @param  bool  $circularLending  Loans went around in a circle (A->B->A or A->B->C->A).
     */
    public function __construct(
        public array $mutualVoucherIds = [],
        public array $ineligibleVoucherIds = [],
        public bool $circularLending = false,
    ) {}

    public static function none(): self
    {
        return new self;
    }
}
