<?php

namespace App\Payments\Exceptions;

use RuntimeException;

/**
 * A payment operation refused before reaching the gateway: refunding more
 * than was captured, capturing a payment that is not authorized, starting a
 * checkout on a closed link. The message is written for whoever asked.
 */
class PaymentNotAllowed extends RuntimeException {}
