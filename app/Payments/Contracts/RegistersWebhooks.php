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
     * to every event this application acts on.
     *
     * What verifying its deliveries needs (a Stripe signing secret, a PayPal
     * webhook id) is handed to $remember the moment the endpoint exists --
     * before any old endpoint is removed -- so a failure part-way through
     * never leaves the stored secret belonging to a deleted endpoint.
     *
     * @param  \Closure(string): void  $remember
     *
     * @throws GatewayException
     * @throws GatewayUnavailable
     */
    public function registerWebhook(string $url, \Closure $remember): void;
}
