<?php

namespace Database\Factories;

use App\Models\TaxRate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaxRate>
 */
class TaxRateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'HST',
            'percentage' => '13.000',
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    /**
     * A named rate, e.g. rate('GST', '5').
     */
    public function rate(string $name, string $percentage): static
    {
        return $this->state(fn (array $attributes): array => ['name' => $name, 'percentage' => $percentage]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['is_active' => false]);
    }
}
