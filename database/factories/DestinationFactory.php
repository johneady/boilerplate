<?php

namespace Database\Factories;

use App\Models\Destination;
use App\Travel\Region;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Destination>
 */
class DestinationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->city();

        return [
            'slug' => Str::slug($name),
            'name' => $name,
            'country' => fake()->country(),
            'region' => fake()->randomElement(Region::cases()),
            'tagline' => fake()->sentence(6),
            'description' => fake()->paragraphs(2, true),
            'best_time' => 'May to September',
            'image_path' => null,
            'image_credit' => null,
            'is_featured' => false,
            'sort_order' => 0,
        ];
    }
}
