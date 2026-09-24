<?php

namespace App\Payments\Contracts;

use App\Models\WebhookEvent;
use App\Payments\Data\GatewayDispute;
use App\Payments\Exceptions\GatewayException;
use App\Payments\Exceptions\GatewayUnavailable;

/**
 * A gateway's disputes: which events are about one, and reading one afresh.
 *
 * Only Stripe and PayPal have disputes; Demo and manual payments do not.
 */
interface DisputeDriver
{
    /**
     * The gateway's id for the dispute an event is about, or null for an
     * event that is not about a dispute. Checked before any other reference.
     */
    public function disputeReference(WebhookEvent $event): ?string;

    /**
     * @throws GatewayException
     * @throws GatewayUnavailable
     */
    public function fetchDispute(string $disputeId): GatewayDispute;
}
