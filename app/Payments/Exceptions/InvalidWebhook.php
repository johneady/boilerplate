<?php

namespace App\Payments\Exceptions;

use RuntimeException;

/**
 * A webhook delivery that failed verification: a bad or missing signature, no
 * signing secret configured, or an event for the other mode.
 */
class InvalidWebhook extends RuntimeException {}
