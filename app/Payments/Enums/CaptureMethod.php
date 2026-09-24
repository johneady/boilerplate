<?php

namespace App\Payments\Enums;

/**
 * Whether a payment is taken at checkout or only held for later.
 *
 * Manual places an authorization -- a hold on the customer's funds -- that an
 * administrator later captures (in full or in part) or voids. Holds expire:
 * Stripe's after about seven days, PayPal's after three (reauthorizable up to
 * 29), which payments:check-authorizations warns about.
 */
enum CaptureMethod: string
{
    case Automatic = 'automatic';

    case Manual = 'manual';
}
