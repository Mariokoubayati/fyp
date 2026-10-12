<?php

namespace Tests\Unit\Services\Lending;

use App\Enums\LoanStatus;
use App\Exceptions\InvalidLoanTransition;
use App\Models\Loan;
use App\Services\Lending\LoanStateMachine;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LoanStateMachineTest extends TestCase
{
    /**
     * @return array<string, array{0: LoanStatus, 1: LoanStatus}>
     */
    public static function allowedTransitions(): array
    {
        return [
            'requested to accepted' => [LoanStatus::Requested, LoanStatus::Accepted],
            'requested to cancelled' => [LoanStatus::Requested, LoanStatus::Cancelled],
            'accepted to active' => [LoanStatus::Accepted, LoanStatus::Active],
            'accepted to cancelled' => [LoanStatus::Accepted, LoanStatus::Cancelled],
            'active to repaid' => [LoanStatus::Active, LoanStatus::Repaid],
            'active to overdue' => [LoanStatus::Active, LoanStatus::Overdue],
            'overdue to repaid' => [LoanStatus::Overdue, LoanStatus::Repaid],
            'overdue to defaulted' => [LoanStatus::Overdue, LoanStatus::Defaulted],
            'defaulted to repaid' => [LoanStatus::Defaulted, LoanStatus::Repaid],
        ];
    }

    /**
     * @return array<string, array{0: LoanStatus, 1: LoanStatus}>
     */
    public static function forbiddenTransitions(): array
    {
        return [
            'requested to active' => [LoanStatus::Requested, LoanStatus::Active],
            'requested to repaid' => [LoanStatus::Requested, LoanStatus::Repaid],
            'accepted to repaid' => [LoanStatus::Accepted, LoanStatus::Repaid],
            'active to defaulted' => [LoanStatus::Active, LoanStatus::Defaulted],
            'active to cancelled' => [LoanStatus::Active, LoanStatus::Cancelled],
            'repaid to active' => [LoanStatus::Repaid, LoanStatus::Active],
            'repaid to overdue' => [LoanStatus::Repaid, LoanStatus::Overdue],
            'cancelled to requested' => [LoanStatus::Cancelled, LoanStatus::Requested],
            'defaulted to overdue' => [LoanStatus::Defaulted, LoanStatus::Overdue],
        ];
    }

    #[DataProvider('allowedTransitions')]
    public function test_allows_valid_transitions(LoanStatus $from, LoanStatus $to): void
    {
        $loan = new Loan(['amount' => 10]);
        $loan->status = $from;

        (new LoanStateMachine)->transition($loan, $to);

        $this->assertSame($to, $loan->status);
    }

    #[DataProvider('forbiddenTransitions')]
    public function test_rejects_invalid_transitions(LoanStatus $from, LoanStatus $to): void
    {
        $loan = new Loan(['amount' => 10]);
        $loan->status = $from;

        $this->expectException(InvalidLoanTransition::class);

        (new LoanStateMachine)->transition($loan, $to);
    }
}
