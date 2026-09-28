<?php

namespace App\Prints;

/**
 * The priced answer to "what do this many prints cost".
 *
 * Carries the whole arithmetic of the deal -- how many bundles, how many
 * loose prints, what the prints would have cost loose, what was saved -- so
 * the wizard, the confirmation screen and the fulfillment console all render
 * from one object instead of each restating the formula and drifting.
 */
final readonly class PrintQuote
{
    public function __construct(
        /** The total number of prints being priced. */
        public int $prints,
        /** The single-print price, in minor units. */
        public int $unitPrice,
        /** The bundle price, in minor units. */
        public int $bundlePrice,
        /** How many whole bundles the prints make up. */
        public int $bundles,
        /** Prints left over beyond the whole bundles. */
        public int $singles,
        /** What the customer pays, in minor units. */
        public int $totalCents,
        /** What the same prints would cost without the deal, in minor units. */
        public int $listTotalCents,
        /** The difference, in minor units. Zero when no bundle applies. */
        public int $savingsCents,
    ) {}

    /**
     * Whether the deal reduced the price at all.
     */
    public function hasSavings(): bool
    {
        return $this->savingsCents > 0;
    }

    /**
     * How many more prints would complete another bundle.
     *
     * The wizard uses this for its nudge: "add one more print and pay the
     * bundle price". Zero when the prints already divide evenly.
     */
    public function printsToNextBundle(): int
    {
        return $this->singles === 0 ? 0 : PrintPricing::BUNDLE_SIZE - $this->singles;
    }

    public function total(): string
    {
        return PrintPricing::money($this->totalCents);
    }

    public function listTotal(): string
    {
        return PrintPricing::money($this->listTotalCents);
    }

    public function savings(): string
    {
        return PrintPricing::money($this->savingsCents);
    }

    public function unit(): string
    {
        return PrintPricing::money($this->unitPrice);
    }

    public function bundle(): string
    {
        return PrintPricing::money($this->bundlePrice);
    }
}
