<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Travel\Price;
use Carbon\CarbonImmutable;
use Database\Factories\TourDepartureFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One scheduled run of a tour, with a fixed number of seats.
 *
 * `seats_held` counts seats taken by CONFIRMED booking requests only. A new
 * request does not hold anything: it is a question until somebody at the
 * agency confirms it, which is what keeps one keen visitor from blocking a
 * departure by submitting the form repeatedly.
 *
 * @property int $id
 * @property int $tour_id
 * @property CarbonImmutable $starts_on
 * @property int|null $price_per_person_cents
 * @property int $seats_total
 * @property int $seats_held
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Tour $tour
 */
#[Fillable(['tour_id', 'starts_on', 'price_per_person_cents', 'seats_total', 'seats_held'])]
class TourDeparture extends Model
{
    /** @use HasFactory<TourDepartureFactory> */
    use Auditable, HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_on' => 'immutable_date',
            'price_per_person_cents' => 'integer',
            'seats_total' => 'integer',
            'seats_held' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Tour, $this>
     */
    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    /**
     * The price per adult on this departure: its own, or the tour's.
     */
    public function pricePerPersonCents(): int
    {
        return $this->price_per_person_cents ?? $this->tour->price_per_person_cents;
    }

    public function formattedPrice(): string
    {
        return Price::format($this->pricePerPersonCents());
    }

    public function seatsLeft(): int
    {
        return max(0, $this->seats_total - $this->seats_held);
    }

    public function isSoldOut(): bool
    {
        return $this->seatsLeft() === 0;
    }

    /**
     * Whether so few seats remain that the site should say so.
     */
    public function isNearlyFull(): bool
    {
        return ! $this->isSoldOut() && $this->seatsLeft() <= (int) config('travel.few_seats_threshold', 4);
    }

    /**
     * The last day of the tour.
     */
    public function endsOn(): CarbonImmutable
    {
        return $this->starts_on->addDays(max(0, $this->tour->duration_days - 1));
    }

    /**
     * "12 May – 19 May 2027".
     */
    public function dateRange(): string
    {
        $end = $this->endsOn();
        $startFormat = $this->starts_on->year === $end->year ? 'j M' : 'j M Y';

        return $this->starts_on->format($startFormat).' – '.$end->format('j M Y');
    }

    /**
     * Whether this departure takes a party of the given size right now.
     */
    public function canSeat(int $travellers): bool
    {
        return $this->starts_on->isFuture() && $this->seatsLeft() >= $travellers;
    }
}
