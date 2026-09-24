<?php

namespace Database\Factories;

use App\Models\Dispute;
use App\Models\Payment;
use App\Payments\Enums\Currency;
use App\Payments\Enums\DisputeStatus;
use App\Payments\Enums\Gateway;
use App\Payments\Enums\GatewayMode;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Dispute>
 */
class DisputeFactory extends Factory
{
    /**
     * An open $50.00 CAD Stripe dispute awaiting a response.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payment_id' => Payment::factory()->gateway(Gateway::Stripe)->paid(),
            'gateway' => Gateway::Stripe,
            'mode' => GatewayMode::Sandbox,
            'gateway_dispute_id' => 'dp_'.Str::random(14),
            'currency' => Currency::CAD,
            'amount' => 5000,
            'reason' => 'fraudulent',
            'status' => DisputeStatus::NeedsResponse,
            'evidence_due_by' => now()->addDays(7),
        ];
    }

    public function status(DisputeStatus $status): static
    {
        return $this->state(fn (array $attributes): array => ['status' => $status]);
    }
}
