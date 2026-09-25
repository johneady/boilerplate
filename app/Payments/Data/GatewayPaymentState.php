<?php

namespace App\Payments\Data;

use Carbon\CarbonImmutable;

/**
 * Everything a gateway currently says about one payment.
 *
 * Fetched fresh from the gateway's API whenever anything changes, and applied
 * by App\Payments\Actions\ReconcilePayment. Webhook payloads are never used as
 * the state itself: they only say that something changed, and a payload can
 * arrive late, twice or out of order, where a fresh read cannot.
 */
final readonly class GatewayPaymentState
{
    /**
     * @param  list<GatewayTransaction>  $charges
     * @param  list<GatewayRefund>  $refunds
     */
    public function __construct(
        public GatewayStatus $status,
        public ?string $paymentId = null,
        public ?string $authorizationId = null,
        public ?CarbonImmutable $authorizationExpiresAt = null,
        public array $charges = [],
        public array $refunds = [],
        public ?string $failureReason = null,
    ) {}
}
