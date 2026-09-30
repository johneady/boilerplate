<?php

namespace Database\Factories;

use App\Models\Order;
use App\Ordering\Fulfilment;
use App\Ordering\OrderStatus;
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
        $subtotal = fake()->numberBetween(800, 6000);

        return [
            'customer_name' => fake()->name(),
            'customer_email' => fake()->safeEmail(),
            'customer_phone' => fake()->numerify('(416) 555-####'),
            'fulfilment' => Fulfilment::Pickup,
            'ready_at' => now()->addHour(),
            'status' => OrderStatus::New,
            'subtotal_cents' => $subtotal,
            'delivery_fee_cents' => 0,
            'total_cents' => $subtotal,
        ];
    }

    public function status(OrderStatus $status): static
    {
        return $this->state(fn (): array => ['status' => $status]);
    }
}
