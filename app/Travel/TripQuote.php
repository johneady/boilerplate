<?php

namespace App\Travel;

use App\Models\Tour;
use App\Models\TourDeparture;
use InvalidArgumentException;

/**
 * The price of one party on one tour departure.
 *
 * Adults pay the departure's price (or the tour's, when the departure has no
 * price of its own). Children pay config('travel.child_price_percent') of it.
 * A lone adult with no children pays the single supplement on top, because
 * they take a room to themselves. The deposit is a share of the total.
 *
 * All arithmetic is in whole cents and child prices round to the nearest cent,
 * so the lines always add up to the total shown.
 */
final readonly class TripQuote
{
    public function __construct(
        public int $adults,
        public int $children,
        public int $adultPriceCents,
        public int $childPriceCents,
        public int $singleSupplementCents,
    ) {}

    /**
     * Quote a party on a tour, optionally on a specific departure.
     */
    public static function for(Tour $tour, ?TourDeparture $departure, int $adults, int $children = 0): self
    {
        if ($adults < 1) {
            throw new InvalidArgumentException('A party needs at least one adult.');
        }

        if ($children < 0) {
            throw new InvalidArgumentException('Children cannot be negative.');
        }

        $adultPrice = $departure?->pricePerPersonCents() ?? $tour->price_per_person_cents;
        $childPercent = (int) config('travel.child_price_percent', 100);

        return new self(
            adults: $adults,
            children: $children,
            adultPriceCents: $adultPrice,
            childPriceCents: (int) round($adultPrice * $childPercent / 100),
            singleSupplementCents: $adults === 1 && $children === 0 ? $tour->single_supplement_cents : 0,
        );
    }

    /**
     * The number of seats this party needs.
     */
    public function travellers(): int
    {
        return $this->adults + $this->children;
    }

    public function adultsTotalCents(): int
    {
        return $this->adults * $this->adultPriceCents;
    }

    public function childrenTotalCents(): int
    {
        return $this->children * $this->childPriceCents;
    }

    public function totalCents(): int
    {
        return $this->adultsTotalCents() + $this->childrenTotalCents() + $this->singleSupplementCents;
    }

    /**
     * The deposit due to secure the seats, rounded up to the whole dollar.
     */
    public function depositCents(): int
    {
        $percent = (int) config('travel.deposit_percent', 20);

        return (int) (ceil($this->totalCents() * $percent / 100 / 100) * 100);
    }
}
