<?php

namespace App\Payments\Data;

/**
 * The state of a payment as the gateway reports it, before it is mapped onto
 * App\Payments\Enums\PaymentStatus.
 *
 * Deliberately coarse: whether money was taken, and how much, comes from the
 * charges and refunds on GatewayPaymentState rather than from this status.
 */
enum GatewayStatus
{
    /** The customer has not finished paying. */
    case Open;

    /** Funds are held, awaiting capture. */
    case Authorized;

    /** Funds were taken; see the charges. */
    case Captured;

    case Failed;

    /** An authorization or checkout was cancelled. */
    case Canceled;

    case Expired;
}
