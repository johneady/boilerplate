<?php

namespace App\Payments\Contracts;

use App\Models\Payment;
use App\Payments\Enums\CaptureMethod;
use App\Payments\Enums\Currency;
use App\Payments\Enums\PaymentAcceptance;
use App\Payments\Exceptions\InvalidPaymentAmount;
use App\Payments\Money;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Something a customer can pay for.
 *
 * The boilerplate's own implementation is App\Models\PaymentLink; a project's
 * Order, Booking or Invoice implements this to become payable through every
 * gateway, with refunds, capture and the admin screens already working.
 * App\Concerns\IsPayable supplies payments() for an Eloquent model.
 *
 * The payable is the only source of the amount. A checkout never trusts a
 * price from the browser: amountDue() computes it server-side, and the one
 * exception -- a customer-entered amount -- is validated there too.
 */
interface Payable
{
    /**
     * Every payment made against this payable.
     *
     * @return MorphMany<Payment, covariant \Illuminate\Database\Eloquent\Model>
     */
    public function payments(): MorphMany;

    /**
     * One line describing what is being paid for, shown at the gateway and on
     * the receipt.
     */
    public function paymentDescription(): string;

    public function paymentCurrency(): Currency;

    /**
     * The pre-tax amount to charge.
     *
     * $offered is the amount the customer entered, for payables that let them
     * choose; a fixed-price payable ignores it.
     *
     * @throws InvalidPaymentAmount when the offered amount is missing or out of range
     */
    public function amountDue(?Money $offered = null): Money;

    /**
     * Whether the configured tax rates apply.
     */
    public function isTaxable(): bool;

    /**
     * Take the money at checkout, or only hold it for an administrator to
     * capture later.
     */
    public function captureMethod(): CaptureMethod;

    /**
     * Whether a new checkout may start now.
     */
    public function acceptsPayments(): bool;

    /**
     * Record that one of this payable's payments has succeeded.
     *
     * Called exactly once per payment, INSIDE the transaction that records the
     * payment as paid, so work done here commits or rolls back with it. Keep
     * it to database writes: anything that leaves the process (mail, HTTP)
     * belongs after commit. Return Duplicate when the payable was already
     * settled by another payment and this one must be refunded.
     */
    public function acceptPayment(Payment $payment): PaymentAcceptance;

    /**
     * Where a customer goes to pay again after cancelling, or null if nowhere.
     */
    public function payableUrl(): ?string;
}
