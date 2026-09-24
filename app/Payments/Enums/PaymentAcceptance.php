<?php

namespace App\Payments\Enums;

/**
 * A payable's answer when told one of its payments succeeded.
 */
enum PaymentAcceptance
{
    case Accepted;

    /**
     * The payable had already been settled by another payment -- two checkouts
     * of a single-use link completed at once. The duplicate is refunded
     * automatically and operators are told.
     */
    case Duplicate;
}
