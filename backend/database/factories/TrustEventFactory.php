<?php

namespace Database\Factories;

use App\Enums\TrustEventType;
use App\Models\Listing;
use App\Models\TrustEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrustEvent>
 */
class TrustEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => TrustEventType::SaleCompleted,
            'subject_type' => (new Listing)->getMorphClass(),
            'subject_id' => Listing::factory(),
            'value' => null,
            'occurred_at' => now(),
        ];
    }

    /**
     * An event of the given type.
     */
    public function ofType(TrustEventType $type, ?int $value = null): static
    {
        return $this->state(fn (): array => ['type' => $type, 'value' => $value]);
    }
}
