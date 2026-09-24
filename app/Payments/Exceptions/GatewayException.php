<?php

namespace App\Payments\Exceptions;

use RuntimeException;

/**
 * The gateway answered, and the answer was no: a declined refund, an invalid
 * request, an authorization that has already been captured.
 *
 * Definitive -- retrying with the same request will get the same answer --
 * so the operation is recorded as failed. The message is safe to show an
 * administrator.
 */
class GatewayException extends RuntimeException {}
