<?php

namespace Database\Factories;

use App\Models\Plan;
use App\Models\PlanPrice;
use App\Payments\Enums\BillingInterval;
use App\Payments\Enums\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlanPrice>
 */
class PlanPriceFactory extends Factory
{
    /**
     * $29.00 CAD a month.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'plan_id' => Plan::factory(),
            'currency' => Currency::CAD,
            'amount' => 2900,
            'interval' => BillingInterval::Month,
            'interval_count' => 1,
            'is_active' => true,
        ];
    }

    public function yearly(int $amount = 29000): static
    {
        return $this->state(fn (array $attributes): array => ['interval' => BillingInterval::Year, 'amount' => $amount]);
    }

    /**
     * Retired: no longer offered, though existing subscribers keep it.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['is_active' => false]);
    }
}
