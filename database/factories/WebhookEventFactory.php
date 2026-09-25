<?php

namespace Database\Factories;

use App\Models\WebhookEvent;
use App\Payments\Enums\Gateway;
use App\Payments\Enums\GatewayMode;
use App\Payments\Enums\WebhookEventStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebhookEvent>
 */
class WebhookEventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'gateway' => Gateway::Stripe,
            'mode' => GatewayMode::Sandbox,
            'event_id' => 'evt_'.fake()->unique()->bothify('????????????????'),
            'type' => 'checkout.session.completed',
            'payload' => ['data' => ['object' => ['id' => 'cs_test_unknown']]],
            'status' => WebhookEventStatus::Received,
            'attempts' => 0,
        ];
    }

    public function failed(string $error = 'Stripe could not be reached.'): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => WebhookEventStatus::Failed,
            'attempts' => 5,
            'error' => $error,
            'processed_at' => now(),
        ]);
    }
}
