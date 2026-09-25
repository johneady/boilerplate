<?php

namespace App\Payments\Contracts;

use App\Models\Payment;
use App\Models\WebhookEvent;
use App\Payments\Data\GatewayRefund;
use App\Payments\Data\PaymentReference;
use App\Payments\Data\SubscriptionReference;
use App\Payments\Data\VerifiedWebhook;
use App\Payments\Exceptions\InvalidWebhook;
use Illuminate\Http\Request;

/**
 * A gateway's webhook deliveries: verifying them, and reading which payment
 * or subscription an event is about.
 */
interface WebhookDriver
{
    /**
     * Check a delivery really came from the gateway, for this mode.
     *
     * @throws InvalidWebhook
     */
    public function verifyWebhook(Request $request): VerifiedWebhook;

    /**
     * Which payment an event concerns, or null for an event type this
     * application does not act on.
     */
    public function paymentReference(WebhookEvent $event): ?PaymentReference;

    /**
     * Whether this event means the customer approved a checkout that still
     * needs completing server-side (PayPal's CHECKOUT.ORDER.APPROVED, for a
     * customer who closed the tab before returning).
     */
    public function completesCheckout(WebhookEvent $event): bool;

    /**
     * Which subscription an event concerns, or null for an event that is not
     * about a subscription. Checked before paymentReference().
     */
    public function subscriptionReference(WebhookEvent $event): ?SubscriptionReference;

    /**
     * A refund the event reports that re-reading the payment would not find,
     * fetched fresh from the gateway, or null.
     *
     * Only PayPal subscription payments need it: a PayPal sale lists no
     * refunds of its own, so one made in the PayPal dashboard is learned
     * from its event.
     */
    public function refundInEvent(WebhookEvent $event, Payment $payment): ?GatewayRefund;
}
