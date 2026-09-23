<?php

namespace Database\Factories;

use App\Models\Enquiry;
use App\Voltiva\DrivingNeed;
use App\Voltiva\EnquiryStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Enquiry>
 */
class EnquiryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => '+34 971 '.fake()->numerify('### ###'),
            'vehicle_id' => null,
            'vehicle_name' => null,
            'location' => 'Palma',
            'driving_needs' => [DrivingNeed::Town->value, DrivingNeed::Commute->value],
            'finance_interest' => false,
            'registration_interest' => true,
            'message' => fake()->sentence(),
            'source' => 'register',
            'locale' => 'en',
            'status' => EnquiryStatus::New,
        ];
    }
}
