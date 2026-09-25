<?php

namespace App\Payments\Actions;

use App\Models\Payment;
use App\Models\TaxRate;
use App\Payments\Contracts\Payable;
use App\Payments\Data\CheckoutRequest;
use App\Payments\Data\CheckoutUrls;
use App\Payments\Enums\PaymentStatus;
use App\Payments\Exceptions\GatewayException;
use App\Payments\Exceptions\GatewayUnavailable;
use App\Payments\Exceptions\PaymentNotAllowed;
use App\Payments\PaymentManager;
use App\Payments\Tax\TaxCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Create a payment for a payable and open the gateway's hosted checkout.
 *
 * Three steps, and deliberately never one transaction around the gateway call
 * (a transaction cannot roll back a session Stripe has already created, and a
 * slow API call must not hold locks):
 *
 *   1. record the pending payment, keyed by the request's idempotency key;
 *   2. ask the gateway for a checkout, keyed by the payment's uuid;
 *   3. record the checkout's id and URL.
 *
 * Resubmitting the same form finds the step-1 row and returns its existing
 * checkout rather than creating a second payment. A definitive gateway
 * refusal marks the payment failed rather than leaving it pending with
 * nowhere to pay; an unreachable one leaves it pending, its outcome unknown,
 * for the abandoned-checkout sweep to settle.
 */
class StartCheckout
{
    public function __construct(
        private readonly PaymentManager $payments,
        private readonly TaxCalculator $taxCalculator,
    ) {}

    /**
     * @throws PaymentNotAllowed when the gateway is not offered or the payable is closed
     * @throws GatewayException when the gateway refuses or cannot be reached
     */
    public function handle(Payable&Model $payable, CheckoutRequest $request): Payment
    {
        $existing = Payment::query()->where('idempotency_key', $request->idempotencyKey)->first();

        if ($existing !== null) {
            return $existing;
        }

        if (! $this->payments->enabled() || ! $this->payments->offers($request->gateway)) {
            throw new PaymentNotAllowed(__('That payment method is not available.'));
        }

        if (! $payable->acceptsPayments()) {
            throw new PaymentNotAllowed(__('This is no longer accepting payments.'));
        }

        $payment = $this->recordPending($payable, $request);

        if ($payment->checkout_url !== null || $payment->status !== PaymentStatus::Pending) {
            return $payment;
        }

        try {
            $session = $this->payments->driverFor($payment)->createCheckout($payment, new CheckoutUrls(
                returnUrl: $payment->returnUrl(),
                cancelUrl: $payment->cancelUrl(),
            ));
        } catch (GatewayUnavailable $e) {
            // The session may or may not exist at the gateway, so the
            // outcome is unknown: leave the payment pending for the
            // abandoned-checkout sweep -- or the return URL, should the
            // customer somehow complete it -- rather than record a failure
            // that may be wrong. The caller reports the exception.
            throw $e;
        } catch (GatewayException $e) {
            $this->markFailed($payment, $e->getMessage());

            throw $e;
        }

        return DB::transaction(function () use ($payment, $session): Payment {
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            $locked->gateway_checkout_id ??= $session->id;
            $locked->checkout_url = $session->url;
            $locked->save();

            return $locked;
        });
    }

    private function recordPending(Payable&Model $payable, CheckoutRequest $request): Payment
    {
        $subtotal = $payable->amountDue($request->offeredAmount);

        if (! $subtotal->isPositive()) {
            throw new PaymentNotAllowed(__('This has nothing to pay.'));
        }

        $breakdown = $this->taxCalculator->calculate(
            $subtotal,
            $payable->isTaxable() ? TaxRate::query()->active()->get()->map->toCalculatorRate() : [],
        );

        try {
            return DB::transaction(fn (): Payment => $payable->payments()->create([
                'idempotency_key' => $request->idempotencyKey,
                'user_id' => $request->userId,
                'customer_name' => $request->customerName,
                'customer_email' => $request->customerEmail,
                'description' => mb_substr($payable->paymentDescription(), 0, 255),
                'gateway' => $request->gateway,
                'mode' => $this->payments->mode(),
                'status' => PaymentStatus::Pending,
                'capture_method' => $payable->captureMethod(),
                'currency' => $subtotal->currency,
                'subtotal' => $breakdown->subtotal->amount,
                'tax_total' => $breakdown->taxTotal()->amount,
                'amount' => $breakdown->total()->amount,
                'tax_lines' => $breakdown->linesToArray(),
            ]));
        } catch (UniqueConstraintViolationException) {
            // The same form submitted twice at once: the other request won
            // the insert, so use its payment.
            return Payment::query()->where('idempotency_key', $request->idempotencyKey)->firstOrFail();
        }
    }

    private function markFailed(Payment $payment, string $reason): void
    {
        DB::transaction(function () use ($payment, $reason): void {
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if (! $locked->status->canTransitionTo(PaymentStatus::Failed)) {
                return;
            }

            $locked->status = PaymentStatus::Failed;
            $locked->failed_at = CarbonImmutable::now();
            $locked->failure_reason = mb_substr($reason, 0, 255);
            $locked->save();
        });
    }
}
