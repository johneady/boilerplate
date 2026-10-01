<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Travel\InquiryStatus;
use App\Travel\Price;
use Carbon\CarbonImmutable;
use Database\Factories\TripInquiryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A booking request from the public site: either for a seat on a scheduled
 * tour departure, or a tailor-made trip described in the traveller's own words.
 *
 * The quoted total is captured when the request is made, so a later price
 * change on the tour never rewrites what the traveller was shown.
 *
 * @property int $id
 * @property string $reference
 * @property int|null $user_id
 * @property int|null $tour_id
 * @property int|null $tour_departure_id
 * @property int|null $destination_id
 * @property InquiryStatus $status
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property int $adults
 * @property int $children
 * @property string|null $travel_month
 * @property int|null $quoted_total_cents
 * @property string|null $message
 * @property string|null $ip_address
 * @property CarbonImmutable|null $confirmed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Tour|null $tour
 * @property-read TourDeparture|null $departure
 * @property-read Destination|null $destination
 * @property-read User|null $user
 */
#[Fillable([
    'user_id', 'tour_id', 'tour_departure_id', 'destination_id', 'name', 'email', 'phone',
    'adults', 'children', 'travel_month', 'quoted_total_cents', 'message', 'ip_address',
])]
class TripInquiry extends Model
{
    /** @use HasFactory<TripInquiryFactory> */
    use Auditable, HasFactory;

    /**
     * Every request starts new, in memory as well as in the column default.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'new',
        'children' => 0,
    ];

    /**
     * Give every request a short reference the traveller can quote on the phone.
     */
    protected static function booted(): void
    {
        static::creating(function (self $inquiry): void {
            if (blank($inquiry->reference)) {
                $inquiry->reference = self::newReference();
            }
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => InquiryStatus::class,
            'adults' => 'integer',
            'children' => 'integer',
            'quoted_total_cents' => 'integer',
            'confirmed_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /**
     * The traveller's own details stay out of the audit trail, like a contact
     * submission's: an audit entry outlives the row it describes.
     *
     * @return list<string>
     */
    protected function auditExclude(): array
    {
        return ['name', 'email', 'phone', 'message', 'ip_address', 'updated_at'];
    }

    /**
     * @return BelongsTo<Tour, $this>
     */
    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    /**
     * @return BelongsTo<TourDeparture, $this>
     */
    public function departure(): BelongsTo
    {
        return $this->belongsTo(TourDeparture::class, 'tour_departure_id');
    }

    /**
     * @return BelongsTo<Destination, $this>
     */
    public function destination(): BelongsTo
    {
        return $this->belongsTo(Destination::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Limit the query to requests nobody has finished with.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function open(Builder $query): void
    {
        $query->whereIn('status', [InquiryStatus::New, InquiryStatus::Contacted]);
    }

    /**
     * Limit the query to requests belonging to a signed-in traveller: made
     * while signed in, or made earlier with the same address.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function belongingTo(Builder $query, User $user): void
    {
        $query->where(fn (Builder $query) => $query
            ->where('user_id', $user->id)
            ->orWhere('email', $user->email));
    }

    public function travellers(): int
    {
        return $this->adults + $this->children;
    }

    /**
     * Whether this is a tailor-made request rather than a scheduled departure.
     */
    public function isCustomTrip(): bool
    {
        return $this->tour_id === null;
    }

    /**
     * What the traveller asked about, in one line.
     */
    public function tripLabel(): string
    {
        if ($this->tour !== null) {
            return $this->tour->name;
        }

        return __('Tailor-made trip: :destination', [
            'destination' => $this->destination !== null ? $this->destination->name : __('anywhere'),
        ]);
    }

    public function formattedQuote(): ?string
    {
        return $this->quoted_total_cents !== null ? Price::format($this->quoted_total_cents) : null;
    }

    /**
     * A reference like "WL-4K7Q2M": short, unambiguous to read aloud, unique.
     */
    public static function newReference(): string
    {
        // No 0/O, 1/I/L: a reference is read down the phone.
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

        do {
            $reference = 'WL-';

            for ($i = 0; $i < 6; $i++) {
                $reference .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (self::query()->where('reference', $reference)->exists());

        return $reference;
    }
}
