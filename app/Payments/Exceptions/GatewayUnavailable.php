<?php

namespace App\Payments\Exceptions;

/**
 * The gateway could not be reached, timed out, rate limited us or failed on
 * its side (a 5xx).
 *
 * NOT definitive: the request may or may not have been carried out. The
 * operation is left pending and retried later with the SAME idempotency key,
 * so the gateway either carries it out or returns what it already did.
 */
class GatewayUnavailable extends GatewayException {}
