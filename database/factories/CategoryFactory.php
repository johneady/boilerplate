<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // words() is declared string|array and returns the array form without
        // the `true`, so the words are joined here rather than cast -- see
        // PageFactory for the reasoning.
        $words = fake()->unique()->words(2);

        $name = implode(' ', is_array($words) ? $words : [$words]);

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name),
        ];
    }
}
