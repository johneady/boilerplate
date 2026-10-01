<?php

namespace Database\Factories;

use App\Models\Destination;
use App\Models\Tour;
use App\Travel\TourStyle;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tour>
 */
class TourFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = rtrim(fake()->unique()->sentence(3), '.').' tour';

        return [
            'destination_id' => Destination::factory(),
            'slug' => Str::slug($name),
            'name' => Str::title($name),
            'style' => fake()->randomElement(TourStyle::cases()),
            'summary' => fake()->sentence(),
            'description' => fake()->paragraphs(2, true),
            'duration_days' => 8,
            'group_size_max' => 12,
            'price_per_person_cents' => 200000,
            'single_supplement_cents' => 50000,
            'highlights' => [fake()->sentence(4)],
            'itinerary' => [['day' => '1', 'title' => 'Arrive', 'body' => fake()->sentence()]],
            'inclusions' => ['Hotels', 'Guide'],
            'is_featured' => false,
            'is_published' => true,
            'sort_order' => 0,
        ];
    }

    public function unpublished(): static
    {
        return $this->state(['is_published' => false]);
    }
}
