<?php

namespace App\Payments\Enums;

/**
 * Where a payment was taken.
 *
 * Stripe and PayPal are real gateways reached over their APIs. Demo is a
 * stand-in that runs the whole flow with no credentials, for client demos and
 * tests, and is refused in production (see App\Payments\PaymentManager).
 * Manual is money an administrator received outside the site -- an Interac
 * e-Transfer, a cheque, cash -- and records by hand.
 *
 * Plain English only, like App\Auth\Role: covered by a unit test with no
 * container, where __() is unavailable. Translate at the point of display.
 */
enum Gateway: string
{
    case Stripe = 'stripe';

    case PayPal = 'paypal';

    case Demo = 'demo';

    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Stripe => 'Stripe',
            self::PayPal => 'PayPal',
            self::Demo => 'Demo',
            self::Manual => 'Manual',
        };
    }

    /**
     * The badge colour in the admin panel. Must be registered on the panel.
     */
    public function color(): string
    {
        return match ($this) {
            self::Stripe => 'info',
            self::PayPal => 'primary',
            self::Demo => 'warning',
            self::Manual => 'zinc',
        };
    }

    /**
     * Whether a customer pays through a hosted page this gateway serves.
     *
     * The three that a customer can choose at checkout. Manual payments are
     * recorded by an administrator after the fact and are never offered.
     */
    public function isHosted(): bool
    {
        return $this !== self::Manual;
    }

    /**
     * Whether the gateway tells us about changes by webhook.
     */
    public function sendsWebhooks(): bool
    {
        return in_array($this, [self::Stripe, self::PayPal], true);
    }

    /**
     * Whether a payment can be authorized now and captured later.
     */
    public function supportsManualCapture(): bool
    {
        return $this !== self::Manual;
    }

    /**
     * Whether a customer can subscribe through this gateway.
     */
    public function supportsSubscriptions(): bool
    {
        return $this !== self::Manual;
    }
}
