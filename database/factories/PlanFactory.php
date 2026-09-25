<?php

namespace Database\Factories;

use App\Models\Plan;
use App\Models\PlanPrice;
use App\Payments\Enums\BillingInterval;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    /**
     * An active, taxable plan with no trial and no prices yet.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Joined the way PageFactory does: words() is declared string|array.
        $words = fake()->unique()->words(2);
        $name = Str::title(implode(' ', is_array($words) ? $words : [$words]));

        return [
            'key' => Str::slug($name),
            'name' => $name,
            'description' => fake()->sentence(),
            'features' => ['Unlimited projects', 'Priority support'],
            'trial_days' => 0,
            'taxable' => true,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function trial(int $days = 14): static
    {
        return $this->state(fn (array $attributes): array => ['trial_days' => $days]);
    }

    /**
     * With one active price.
     */
    public function withPrice(int $amount = 2900, BillingInterval $interval = BillingInterval::Month): static
    {
        return $this->has(PlanPrice::factory()->state(['amount' => $amount, 'interval' => $interval]), 'prices');
    }
}
