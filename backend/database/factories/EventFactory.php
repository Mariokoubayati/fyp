<?php

namespace Database\Factories;

use App\Enums\CampusTag;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = now()->addDays(fake()->numberBetween(2, 20))->setTime(fake()->numberBetween(9, 20), 0);

        return [
            'creator_id' => User::factory(),
            'course_id' => null,
            'title' => fake()->sentence(3),
            'description' => fake()->optional()->sentence(),
            'location' => fake()->randomElement(['Main auditorium', 'Cafeteria', 'Sports hall', 'Room B204']),
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHours(2),
            'capacity' => null,
            'tags' => [fake()->randomElement(CampusTag::cases())],
        ];
    }

    /**
     * Add the creator as the first attendee, like the API does.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Event $event): void {
            $event->attendees()->syncWithoutDetaching([$event->creator_id]);
        });
    }

    /**
     * An event that has already started.
     */
    public function past(): static
    {
        return $this->state(fn (): array => ['starts_at' => now()->subHour(), 'ends_at' => now()->addHour()]);
    }
}
