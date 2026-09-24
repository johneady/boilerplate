<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\PaymentLink;
use App\Models\PaymentTransaction;
use App\Payments\Enums\CaptureMethod;
use App\Payments\Enums\Currency;
use App\Payments\Enums\Gateway;
use App\Payments\Enums\GatewayMode;
use App\Payments\Enums\PaymentStatus;
use App\Payments\Enums\TransactionSource;
use App\Payments\Enums\TransactionType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * A payment row made directly, for screens and queries.
 *
 * Tests of how money moves should go through the real actions (StartCheckout,
 * the Demo gateway, RefundPayment) instead: this factory writes the row and,
 * for paid(), a matching ledger entry, but runs none of the rules.
 *
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * A pending $50.00 CAD Demo payment in sandbox mode.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payable_type' => (new PaymentLink)->getMorphClass(),
            'payable_id' => PaymentLink::factory(),
            'idempotency_key' => 'factory:'.Str::uuid(),
            'customer_name' => fake()->name(),
            'customer_email' => fake()->safeEmail(),
            'description' => fake()->words(3, true),
            'gateway' => Gateway::Demo,
            'mode' => GatewayMode::Sandbox,
            'status' => PaymentStatus::Pending,
            'capture_method' => CaptureMethod::Automatic,
            'currency' => Currency::CAD,
            'subtotal' => 5000,
            'tax_total' => 0,
            'amount' => 5000,
            'tax_lines' => [],
        ];
    }

    /**
     * Paid in full, with the charge on the ledger and the projections to match.
     */
    public function paid(): static
    {
        return $this->afterCreating(function (Payment $payment): void {
            PaymentTransaction::query()->create([
                'payment_id' => $payment->id,
                'type' => TransactionType::Charge,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'gateway' => $payment->gateway,
                'gateway_transaction_id' => 'factory_ch_'.$payment->uuid,
                'source' => TransactionSource::Admin,
                'occurred_at' => now(),
            ]);

            $payment->forceFill([
                'status' => PaymentStatus::Succeeded,
                'amount_captured' => $payment->amount,
                'captured_at' => now(),
                'paid_at' => now(),
                'receipt_sent_at' => now(),
            ])->save();
        });
    }

    public function gateway(Gateway $gateway): static
    {
        return $this->state(fn (array $attributes): array => ['gateway' => $gateway]);
    }

    public function live(): static
    {
        return $this->state(fn (array $attributes): array => ['mode' => GatewayMode::Live]);
    }
}
