<?php

namespace Database\Factories;

use App\Models\Vehicle;
use App\Voltiva\VehicleCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Vehicle>
 */
class VehicleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Unpublished by default, like the column: a test that wants a car on the
     * public site says so with ->published().
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = 'Voltiva '.Str::title(fake()->unique()->word());

        return [
            'slug' => Str::slug($name),
            'name' => $name,
            'category' => VehicleCategory::L6e,
            'tagline' => fake()->sentence(5),
            'summary' => fake()->sentence(14),
            'description' => fake()->paragraph(),
            'price_cents' => fake()->numberBetween(80, 180) * 10000,
            'monthly_from_cents' => fake()->numberBetween(12, 30) * 1000,
            'top_speed_kmh' => 45,
            'range_km' => fake()->numberBetween(70, 160),
            'battery_voltage' => 72,
            'battery_capacity_ah' => 150,
            'battery_kwh' => '10.8',
            'battery_chemistry' => 'LiFePO4',
            'charge_hours' => '6.5',
            'motor_kw' => '4.0',
            'seats' => 2,
            'length_mm' => 2500,
            'width_mm' => 1400,
            'height_mm' => 1550,
            'kerb_weight_kg' => 420,
            'warranty_years' => 2,
            'battery_warranty_years' => 5,
            'equipment' => ['Air conditioning', 'Reversing camera'],
            'key_benefits' => [['title' => 'Easy to park', 'body' => fake()->sentence()]],
            'comfort' => fake()->sentence(),
            'safety' => fake()->sentence(),
            'faqs' => [['question' => 'Can I charge at home?', 'answer' => 'Yes, from a normal socket.']],
            'is_published' => false,
            'is_featured' => false,
            'sort_order' => 0,
        ];
    }

    /**
     * Indicate that the car is shown on the public site.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes): array => ['is_published' => true]);
    }

    /**
     * Indicate that the car is an L7e heavy quadricycle.
     */
    public function l7e(): static
    {
        return $this->state(fn (array $attributes): array => [
            'category' => VehicleCategory::L7e,
            'top_speed_kmh' => 85,
            'motor_kw' => '12.0',
        ]);
    }
}
