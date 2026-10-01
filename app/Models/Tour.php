<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Travel\Price;
use App\Travel\TourStyle;
use Carbon\CarbonImmutable;
use Database\Factories\TourFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A packaged, guided trip with a fixed itinerary and scheduled departures.
 *
 * @property int $id
 * @property int $destination_id
 * @property string $slug
 * @property string $name
 * @property TourStyle $style
 * @property string $summary
 * @property string $description
 * @property int $duration_days
 * @property int $group_size_max
 * @property int $price_per_person_cents
 * @property int $single_supplement_cents
 * @property list<string>|null $highlights
 * @property list<array{day: string, title: string, body: string}>|null $itinerary
 * @property list<string>|null $inclusions
 * @property string|null $image_path
 * @property string|null $image_credit
 * @property bool $is_featured
 * @property bool $is_published
 * @property int $sort_order
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Destination $destination
 */
#[Fillable([
    'destination_id', 'slug', 'name', 'style', 'summary', 'description', 'duration_days', 'group_size_max',
    'price_per_person_cents', 'single_supplement_cents', 'highlights', 'itinerary', 'inclusions',
    'image_path', 'image_credit', 'is_featured', 'is_published', 'sort_order',
])]
class Tour extends Model
{
    /** @use HasFactory<TourFactory> */
    use Auditable, HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'style' => TourStyle::class,
            'duration_days' => 'integer',
            'group_size_max' => 'integer',
            'price_per_person_cents' => 'integer',
            'single_supplement_cents' => 'integer',
            'highlights' => 'array',
            'itinerary' => 'array',
            'inclusions' => 'array',
            'is_featured' => 'boolean',
            'is_published' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Tour pages are addressed by slug.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return BelongsTo<Destination, $this>
     */
    public function destination(): BelongsTo
    {
        return $this->belongsTo(Destination::class);
    }

    /**
     * @return HasMany<TourDeparture, $this>
     */
    public function departures(): HasMany
    {
        return $this->hasMany(TourDeparture::class)->orderBy('starts_on');
    }

    /**
     * Departures a traveller can still ask to join: in the future, with seats.
     *
     * @return HasMany<TourDeparture, $this>
     */
    public function bookableDepartures(): HasMany
    {
        return $this->departures()
            ->where('starts_on', '>', now()->toDateString())
            ->whereColumn('seats_held', '<', 'seats_total');
    }

    /**
     * Limit the query to tours shown on the public site.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('is_published', true);
    }

    /**
     * Order the query the way the site lists tours.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * The "from" price, formatted.
     */
    public function formattedPrice(): string
    {
        return Price::format($this->price_per_person_cents);
    }

    /**
     * The tour's own photo, falling back to its destination's.
     */
    public function imageUrl(): ?string
    {
        if (filled($this->image_path)) {
            return '/storage/'.ltrim($this->image_path, '/');
        }

        return $this->destination->imageUrl();
    }

    /**
     * The credit owed for whichever photo imageUrl() resolved to.
     */
    public function imageCredit(): ?string
    {
        return filled($this->image_path) ? $this->image_credit : $this->destination->image_credit;
    }

    /**
     * "8 days / 7 nights".
     */
    public function durationLabel(): string
    {
        return trans_choice(':days day|:days days', $this->duration_days, ['days' => $this->duration_days])
            .' / '
            .trans_choice(':nights night|:nights nights', max(0, $this->duration_days - 1), ['nights' => max(0, $this->duration_days - 1)]);
    }
}
