<?php

namespace Database\Factories;

use App\Models\Package;
use App\Shop\Region;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Package>
 */
class PackageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Inactive and unlimited by default, matching the column defaults: a test
     * that wants a package in the storefront says so with ->active(), and one
     * that cares about stock says so with ->limited() or ->soldOut().
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $location = fake()->unique()->city();

        return [
            'slug' => Str::slug($location),
            'title' => $location.' from Above',
            'location' => $location,
            'country' => fake()->country(),
            'region' => fake()->randomElement(Region::cases()),
            'summary' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'price_cents' => fake()->numberBetween(49, 499) * 100,
            'resolution' => '4K UHD',
            'frame_rate' => 30,
            'clip_count' => fake()->numberBetween(4, 20),
            'duration_seconds' => fake()->numberBetween(60, 900),
            'stock' => null,
            'is_active' => false,
            'is_featured' => false,
            'image_path' => null,
            'image_credit' => null,
            'sort_order' => 0,
        ];
    }

    /**
     * Indicate that the package is listed in the storefront.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => true,
        ]);
    }

    /**
     * Indicate that the package has a limited number of licences left.
     */
    public function limited(int $stock): static
    {
        return $this->state(fn (array $attributes): array => [
            'stock' => $stock,
        ]);
    }

    /**
     * Indicate that the package has no licences left.
     */
    public function soldOut(): static
    {
        return $this->limited(0);
    }
}
