<?php

namespace App\Services\Trust;

/**
 * The result of one score calculation.
 */
final readonly class TrustScore
{
    /**
     * @param  array<string, int>  $breakdown  Points contributed by each component, after caps.
     * @param  list<string>  $flags  Reasons the tier was held back (unverified email, new account, circular lending).
     */
    public function __construct(
        public int $score,
        public string $tier,
        public int $maxLoan,
        public array $breakdown,
        public array $flags = [],
    ) {}
}
