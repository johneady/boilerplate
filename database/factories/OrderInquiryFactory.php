<?php

namespace Database\Factories;

use App\Bakery\Fulfilment;
use App\Bakery\InquiryStatus;
use App\Models\OrderInquiry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderInquiry>
 */
class OrderInquiryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 3);

        return [
            'reference' => OrderInquiry::REFERENCE_PREFIX.strtoupper(fake()->unique()->bothify('??####')),
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'fulfilment' => Fulfilment::Pickup,
            'needed_on' => now()->addDays(fake()->numberBetween(3, 20))->toDateString(),
            'delivery_address' => null,
            'occasion' => null,
            'items' => [[
                'menu_item_id' => null,
                'name' => 'Cinnamon Rolls',
                'price_unit' => 'half dozen',
                'price_cents' => 2400,
                'quantity' => $quantity,
            ]],
            'estimated_total_cents' => 2400 * $quantity,
            'details' => fake()->sentence(),
            'allergies' => null,
            'status' => InquiryStatus::New,
            'quoted_total_cents' => null,
            'baker_notes' => null,
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
        ];
    }

    /**
     * Put the inquiry at the given stage.
     */
    public function status(InquiryStatus $status): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => $status,
        ]);
    }
}
