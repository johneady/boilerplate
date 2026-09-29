<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\Perfume;
use App\Perfumes\Enums\Audience;
use App\Perfumes\Enums\Concentration;
use App\Perfumes\Enums\Family;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Perfume>
 */
class PerfumeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = Str::title(fake()->unique()->word().' '.fake()->word());

        return [
            'external_id' => (string) Str::uuid(),
            'brand_id' => Brand::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'release_year' => fake()->numberBetween(1950, 2025),
            'concentration' => fake()->randomElement(Concentration::cases()),
            'gender' => fake()->randomElement(Audience::cases()),
            'family' => fake()->randomElement(Family::cases()),
            'perfumer' => fake()->name(),
            'top_notes' => ['Bergamot', 'Pink pepper'],
            'heart_notes' => ['Rose', 'Jasmine'],
            'base_notes' => ['Sandalwood', 'Vanilla'],
            'description' => fake()->sentence(),
        ];
    }
}
