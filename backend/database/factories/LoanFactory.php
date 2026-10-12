<?php

namespace Database\Factories;

use App\Enums\LoanStatus;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Loan>
 */
class LoanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'borrower_id' => User::factory(),
            'lender_id' => null,
            'amount' => fake()->numberBetween(5, 20),
            'purpose' => fake()->optional()->sentence(4),
            'due_date' => now()->addDays(14)->toDateString(),
            'status' => LoanStatus::Requested,
        ];
    }

    /**
     * A request a lender has accepted; waiting for the cash to change hands.
     */
    public function accepted(): static
    {
        return $this->state(fn (): array => [
            'lender_id' => User::factory(),
            'status' => LoanStatus::Accepted,
        ]);
    }

    /**
     * Money handed over and not yet paid back.
     */
    public function active(): static
    {
        return $this->state(fn (): array => [
            'lender_id' => User::factory(),
            'status' => LoanStatus::Active,
            'lender_handover_at' => now()->subDays(3),
            'borrower_handover_at' => now()->subDays(3),
            'activated_at' => now()->subDays(3),
        ]);
    }

    /**
     * Past its due date and not yet paid back.
     */
    public function overdue(): static
    {
        return $this->active()->state(fn (): array => [
            'status' => LoanStatus::Overdue,
            'due_date' => now()->subDays(3)->toDateString(),
        ]);
    }
}
