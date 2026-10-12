<?php

namespace App\Services\Trust;

/**
 * The result of one score calculation.
 */
final readonly class TrustScore
{
    /**
     * @param  array<string, int>  $breakdown  Points contributed by each component, after caps.
     */
    public function __construct(
        public int $score,
        public string $tier,
        public int $maxLoan,
        public array $breakdown,
    ) {}
}
