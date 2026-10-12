<?php

namespace Database\Factories;

use App\Enums\ListingCategory;
use App\Enums\ListingCondition;
use App\Enums\ListingStatus;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Listing>
 */
class ListingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'seller_id' => User::factory(),
            'course_id' => null,
            'title' => fake()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'price' => fake()->randomFloat(2, 1, 150),
            'category' => fake()->randomElement(ListingCategory::cases()),
            'condition' => fake()->randomElement(ListingCondition::cases()),
            'status' => ListingStatus::Available,
        ];
    }

    /**
     * A listing that was sold to the given buyer.
     */
    public function soldTo(User $buyer): static
    {
        return $this->state(fn (): array => [
            'status' => ListingStatus::Sold,
            'buyer_id' => $buyer->id,
            'sold_at' => now(),
        ]);
    }

    /**
     * A listing that is on hold for a buyer.
     */
    public function reserved(): static
    {
        return $this->state(fn (): array => ['status' => ListingStatus::Reserved]);
    }
}
