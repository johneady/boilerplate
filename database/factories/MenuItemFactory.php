<?php

namespace Database\Factories;

use App\Bakery\MenuCategory;
use App\Models\MenuItem;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MenuItem>
 */
class MenuItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = Str::title(rtrim(fake()->unique()->sentence(3), '.'));

        return [
            'slug' => Str::slug($name),
            'name' => $name,
            'category' => fake()->randomElement(MenuCategory::cases()),
            'description' => fake()->sentence(12),
            'price_cents' => fake()->numberBetween(8, 60) * 100,
            'price_unit' => fake()->randomElement(['per loaf', 'half dozen', 'dozen', '8-inch cake']),
            'serves' => null,
            'dietary' => [],
            'notice_days' => 2,
            'is_available' => true,
            'is_seasonal' => false,
            'is_featured' => false,
            'image_path' => null,
            'image_credit' => null,
            'sort_order' => 0,
        ];
    }

    /**
     * Indicate that the item is switched off for now.
     */
    public function unavailable(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_available' => false,
        ]);
    }
}
