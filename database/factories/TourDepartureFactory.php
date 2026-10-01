<?php

namespace Database\Factories;

use App\Models\Tour;
use App\Models\TourDeparture;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TourDeparture>
 */
class TourDepartureFactory extends Factory
{
    /**
     * Define the model's default state: a month out, empty.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tour_id' => Tour::factory(),
            'starts_on' => now()->addMonth()->toDateString(),
            'price_per_person_cents' => null,
            'seats_total' => 12,
            'seats_held' => 0,
        ];
    }

    /**
     * Leave only the given number of seats.
     */
    public function seatsLeft(int $seats): static
    {
        return $this->state(fn (array $attributes): array => [
            'seats_held' => $attributes['seats_total'] - $seats,
        ]);
    }
}
