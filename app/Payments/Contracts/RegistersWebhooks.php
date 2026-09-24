<?php

namespace App\Payments\Contracts;

use App\Payments\Exceptions\GatewayException;
use App\Payments\Exceptions\GatewayUnavailable;

/**
 * Create this site's webhook endpoint at a gateway, through its API.
 */
interface RegistersWebhooks
{
    /**
     * Make sure the gateway has exactly one endpoint at this URL, subscribed
     * to every event this application acts on, and return what verifying its
     * deliveries needs (a Stripe signing secret, a PayPal webhook id).
     *
     * @throws GatewayException
     * @throws GatewayUnavailable
     */
    public function registerWebhook(string $url): string;
}
