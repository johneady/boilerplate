<?php

namespace Database\Factories;

use App\Models\Product;
use App\Ordering\ProductCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = Str::title(fake()->unique()->words(3, true));

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'category' => fake()->randomElement(ProductCategory::cases()),
            'description' => fake()->sentence(12),
            'price_cents' => fake()->numberBetween(250, 4500),
            'is_available' => true,
            'is_featured' => false,
            'sort_order' => 0,
        ];
    }

    public function unavailable(): static
    {
        return $this->state(fn (): array => ['is_available' => false]);
    }
}
