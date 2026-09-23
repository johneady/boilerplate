<?php

namespace Database\Factories;

use App\Models\Order;
use App\Shop\OrderStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference' => Order::newReference(),
            'user_id' => null,
            'customer_name' => fake()->name(),
            'customer_email' => fake()->safeEmail(),
            'status' => OrderStatus::Paid,
            'total_cents' => fake()->numberBetween(49, 499) * 100,
            'fulfilled_at' => null,
        ];
    }

    /**
     * Indicate that the order's download links have been delivered.
     */
    public function fulfilled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => OrderStatus::Fulfilled,
            'fulfilled_at' => now(),
        ]);
    }

    /**
     * Indicate that the order has been refunded.
     */
    public function refunded(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => OrderStatus::Refunded,
        ]);
    }
}
