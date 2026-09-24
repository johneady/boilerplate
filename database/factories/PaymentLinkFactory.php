<?php

namespace Database\Factories;

use App\Models\PaymentLink;
use App\Payments\Enums\Currency;
use App\Payments\Enums\PaymentLinkAmountType;
use App\Payments\Enums\PaymentLinkUsage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentLink>
 */
class PaymentLinkFactory extends Factory
{
    /**
     * A reusable, untaxed $50.00 CAD link.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'amount_type' => PaymentLinkAmountType::Fixed,
            'amount' => 5000,
            'min_amount' => null,
            'max_amount' => null,
            'currency' => Currency::CAD,
            'taxable' => false,
            'usage' => PaymentLinkUsage::Reusable,
            'expires_at' => null,
            'is_active' => true,
        ];
    }

    /**
     * A fixed price, in minor units.
     */
    public function costing(int $amount): static
    {
        return $this->state(fn (array $attributes): array => [
            'amount_type' => PaymentLinkAmountType::Fixed,
            'amount' => $amount,
        ]);
    }

    /**
     * The customer enters the amount, optionally bounded (minor units).
     */
    public function customerEntered(?int $min = 100, ?int $max = 100_000): static
    {
        return $this->state(fn (array $attributes): array => [
            'amount_type' => PaymentLinkAmountType::CustomerEntered,
            'amount' => null,
            'min_amount' => $min,
            'max_amount' => $max,
        ]);
    }

    public function singleUse(): static
    {
        return $this->state(fn (array $attributes): array => ['usage' => PaymentLinkUsage::SingleUse]);
    }

    public function taxable(): static
    {
        return $this->state(fn (array $attributes): array => ['taxable' => true]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => ['expires_at' => now()->subDay()]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['is_active' => false]);
    }
}
