<?php

namespace Database\Factories;

use App\Models\TourDeparture;
use App\Models\TripInquiry;
use App\Travel\InquiryStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TripInquiry>
 */
class TripInquiryFactory extends Factory
{
    /**
     * Define the model's default state: a new tailor-made request.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => null,
            'adults' => 2,
            'children' => 0,
            'travel_month' => 'June 2027',
            'message' => fake()->sentence(),
        ];
    }

    /**
     * A request for seats on the given departure.
     */
    public function forDeparture(TourDeparture $departure): static
    {
        return $this->state([
            'tour_id' => $departure->tour_id,
            'tour_departure_id' => $departure->id,
            'travel_month' => null,
        ]);
    }

    public function status(InquiryStatus $status): static
    {
        return $this->afterMaking(function (TripInquiry $inquiry) use ($status): void {
            $inquiry->status = $status;
        });
    }
}
