<?php

namespace App\Payments\Exceptions;

use RuntimeException;

/**
 * The amount offered for a payable is missing, malformed or out of range.
 *
 * The message is written for the customer: the checkout form shows it beside
 * the amount field.
 */
class InvalidPaymentAmount extends RuntimeException {}
