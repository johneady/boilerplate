<?php

namespace App\Payments\Data;

use App\Payments\Money;
use App\Payments\Tax\TaxLine;
use Carbon\CarbonImmutable;

/**
 * One paid subscription invoice, as a gateway reports it.
 *
 * Becomes one App\Models\Payment. Tax on subscriptions is calculated by the
 * gateway, so the lines here are what the gateway charged, not a recalculation.
 */
final readonly class GatewayInvoice
{
    /**
     * @param  list<TaxLine>  $taxLines
     */
    public function __construct(
        /** The gateway's id for the invoice (a Stripe invoice, a PayPal transaction). */
        public string $id,
        /** What the payment is refunded through (a Stripe PaymentIntent, a PayPal capture). */
        public string $paymentId,
        public Money $total,
        public array $taxLines,
        public CarbonImmutable $paidAt,
    ) {}

    public function taxTotal(): Money
    {
        $total = Money::zero($this->total->currency);

        foreach ($this->taxLines as $line) {
            $total = $total->add($line->amount);
        }

        return $total;
    }

    public function subtotal(): Money
    {
        return $this->total->subtract($this->taxTotal());
    }
}
