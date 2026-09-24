<?php

namespace App\Payments\Contracts;

use App\Models\Payment;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\Subscription;
use App\Payments\Data\CheckoutSession;
use App\Payments\Data\CheckoutUrls;
use App\Payments\Data\GatewayInvoice;
use App\Payments\Data\GatewayPaymentState;
use App\Payments\Data\GatewaySubscriptionState;
use App\Payments\Exceptions\GatewayException;
use App\Payments\Exceptions\GatewayUnavailable;

/**
 * One gateway's side of recurring billing.
 *
 * Bound to one set of credentials, like PaymentDriver. Drivers talk to the
 * gateway and record only the gateway's catalogue ids (a synced price's id, a
 * customer id); subscription state is written by
 * App\Payments\Actions\ReconcileSubscription from fetchSubscription().
 *
 * Every method may throw GatewayException (a definitive refusal) or
 * GatewayUnavailable (the outcome is unknown; retry).
 */
interface SubscriptionDriver
{
    /**
     * Make sure the gateway has this plan's product and every one of its
     * prices, creating or replacing what is missing or out of date, and
     * archiving prices that are no longer active.
     *
     * @throws GatewayException
     * @throws GatewayUnavailable
     */
    public function syncPlan(Plan $plan): void;

    /**
     * Whether syncPlan() has nothing left to do for this plan.
     */
    public function isSynced(Plan $plan): bool;

    /**
     * Open the gateway's hosted page where the customer approves the
     * subscription and gives a payment method.
     */
    public function createSubscriptionCheckout(Subscription $subscription, CheckoutUrls $urls): CheckoutSession;

    /**
     * Stop an abandoned subscription checkout from being completed later,
     * where the gateway allows that.
     */
    public function expireSubscriptionCheckout(Subscription $subscription): void;

    public function fetchSubscription(Subscription $subscription): GatewaySubscriptionState;

    /**
     * What the gateway says about the payment behind a paid invoice, read
     * the same way fetch() reads a one-time payment, so a later refund or
     * reconcile of it agrees with how it was first recorded.
     */
    public function invoicePayment(Payment $payment, GatewayInvoice $invoice): GatewayPaymentState;

    /**
     * End the subscription now, or schedule it to end when the paid period does.
     */
    public function cancelSubscription(Subscription $subscription, bool $atPeriodEnd): void;

    /**
     * Take back a cancellation scheduled for the period end.
     */
    public function resumeSubscription(Subscription $subscription): void;

    /**
     * Move the subscription to another price.
     *
     * Returns a URL when the customer must approve the change at the gateway
     * (PayPal), or null when it has been made (Stripe, with prorations).
     */
    public function swapSubscription(Subscription $subscription, PlanPrice $price, CheckoutUrls $urls): ?string;

    /**
     * Where the customer changes the card or account the subscription is
     * billed to, or null when the gateway offers no such page.
     */
    public function paymentMethodUrl(Subscription $subscription, string $returnUrl): ?string;

    /**
     * Whether a cancellation at period end is carried out by this
     * application rather than the gateway (payments:end-subscriptions).
     *
     * True for PayPal, whose subscriptions have no such option: the
     * subscription is suspended, and cancelled here when the period ends.
     */
    public function endsCancelledSubscriptionsLocally(): bool;
}
