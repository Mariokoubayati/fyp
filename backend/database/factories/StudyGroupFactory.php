<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\StudyGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudyGroup>
 */
class StudyGroupFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'creator_id' => User::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->sentence(),
            'location' => fake()->optional()->randomElement(['Library, 2nd floor', 'Cafeteria', 'Room B204']),
            'starts_at' => now()->addDays(fake()->numberBetween(1, 14)),
            'capacity' => fake()->numberBetween(3, 8),
        ];
    }

    /**
     * Add the creator as the first member, like the API does.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (StudyGroup $group): void {
            $group->members()->syncWithoutDetaching([$group->creator_id]);
        });
    }

    /**
     * A group that has already started.
     */
    public function past(): static
    {
        return $this->state(fn (): array => ['starts_at' => now()->subHour()]);
    }
}
