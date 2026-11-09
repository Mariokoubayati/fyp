<?php

namespace Database\Factories;

use App\Enums\ReportReason;
use App\Models\User;
use App\Models\UserReport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserReport>
 */
class UserReportFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reporter_id' => User::factory(),
            'reported_id' => User::factory(),
            'reason' => fake()->randomElement(ReportReason::cases()),
            'details' => null,
        ];
    }
}
