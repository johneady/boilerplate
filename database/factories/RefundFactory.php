<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\Refund;
use App\Payments\Enums\Currency;
use App\Payments\Enums\Gateway;
use App\Payments\Enums\RefundStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Refund>
 */
class RefundFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payment_id' => Payment::factory(),
            'idempotency_key' => 'factory:'.Str::uuid(),
            'gateway' => Gateway::Demo,
            'currency' => Currency::CAD,
            'amount' => 1000,
            'tax_amount' => 0,
            'reason' => null,
            'status' => RefundStatus::Pending,
        ];
    }
}
