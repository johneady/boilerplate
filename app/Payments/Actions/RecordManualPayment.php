<?php

namespace App\Payments\Actions;

use App\Models\Payment;
use App\Models\TaxRate;
use App\Models\User;
use App\Payments\Contracts\Payable;
use App\Payments\Enums\CaptureMethod;
use App\Payments\Enums\Gateway;
use App\Payments\Enums\ManualPaymentMethod;
use App\Payments\Enums\PaymentStatus;
use App\Payments\Enums\TransactionSource;
use App\Payments\Exceptions\PaymentNotAllowed;
use App\Payments\Money;
use App\Payments\PaymentManager;
use App\Payments\Tax\TaxCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Record money received outside the site against a payable.
 *
 * The administrator enters the pre-tax amount, and tax is worked out exactly
 * as at checkout, so a manual payment's receipt reads like any other. The
 * payment is then settled through ReconcilePayment with the Manual driver --
 * the same ledger entry, payable notification and receipt as a card payment.
 */
class RecordManualPayment
{
    public function __construct(
        private readonly PaymentManager $payments,
        private readonly TaxCalculator $taxCalculator,
        private readonly ReconcilePayment $reconcile,
    ) {}

    /**
     * @throws PaymentNotAllowed
     */
    public function handle(
        Payable&Model $payable,
        Money $subtotal,
        ManualPaymentMethod $method,
        ?string $reference,
        CarbonImmutable $receivedOn,
        string $customerName,
        string $customerEmail,
        string $idempotencyKey,
        User $recorder,
    ): Payment {
        if (! $this->payments->manualPaymentsEnabled()) {
            throw new PaymentNotAllowed(__('Manual payments are switched off in the payment settings.'));
        }

        // Checked before anything is written: recording a payment against a
        // settled single-use link would be accepted as a duplicate and
        // "refunded" automatically, which is not what anyone entering it meant.
        if (! $payable->acceptsPayments()) {
            throw new PaymentNotAllowed(__('This is no longer accepting payments.'));
        }

        if (! $subtotal->isPositive() || $subtotal->currency !== $payable->paymentCurrency()) {
            throw new PaymentNotAllowed(__('Enter the amount received.'));
        }

        $breakdown = $this->taxCalculator->calculate(
            $subtotal,
            $payable->isTaxable() ? TaxRate::query()->active()->get()->map->toCalculatorRate() : [],
        );

        try {
            $payment = DB::transaction(fn (): Payment => Payment::query()->where('idempotency_key', $idempotencyKey)->first()
                ?? $payable->payments()->create([
                    'idempotency_key' => $idempotencyKey,
                    'customer_name' => $customerName,
                    'customer_email' => $customerEmail,
                    'description' => mb_substr($payable->paymentDescription(), 0, 255),
                    'gateway' => Gateway::Manual,
                    'mode' => $this->payments->mode(),
                    'status' => PaymentStatus::Pending,
                    'capture_method' => CaptureMethod::Automatic,
                    'currency' => $subtotal->currency,
                    'subtotal' => $breakdown->subtotal->amount,
                    'tax_total' => $breakdown->taxTotal()->amount,
                    'amount' => $breakdown->total()->amount,
                    'tax_lines' => $breakdown->linesToArray(),
                    'manual_method' => $method,
                    'manual_reference' => $reference !== null ? mb_substr($reference, 0, 255) : null,
                    'manual_received_on' => $receivedOn,
                    'recorded_by' => $recorder->id,
                ]));
        } catch (UniqueConstraintViolationException) {
            $payment = Payment::query()->where('idempotency_key', $idempotencyKey)->firstOrFail();
        }

        return $this->reconcile->handle($payment, TransactionSource::Admin);
    }
}
