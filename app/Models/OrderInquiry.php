<?php

namespace App\Models;

use App\Bakery\Fulfilment;
use App\Bakery\InquiryStatus;
use App\Bakery\Money;
use App\Bakery\Occasion;
use App\Concerns\Auditable;
use Carbon\CarbonImmutable;
use Database\Factories\OrderInquiryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A customer's request for an order, sent through the public order form.
 *
 * A request, not a sale: a home bakery confirms the date against its kitchen
 * and replies with a price before anything is baked, so the form collects what
 * the baker needs to quote and this row carries it through to collection.
 *
 * Stored before any email is attempted, like a contact submission -- the
 * mailer's unsaved default is 'log', and an email-only form would lose every
 * order until somebody configured SMTP.
 *
 * @property int $id
 * @property string $reference
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property Fulfilment $fulfilment
 * @property CarbonImmutable $needed_on
 * @property string|null $delivery_address
 * @property Occasion|null $occasion
 * @property list<array{menu_item_id: int|null, name: string, price_unit: string, price_cents: int, quantity: int}> $items
 * @property int $estimated_total_cents
 * @property string|null $details
 * @property string|null $allergies
 * @property InquiryStatus $status
 * @property int|null $quoted_total_cents
 * @property string|null $baker_notes
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable([
    'reference', 'name', 'email', 'phone', 'fulfilment', 'needed_on', 'delivery_address', 'occasion',
    'items', 'estimated_total_cents', 'details', 'allergies', 'status', 'quoted_total_cents',
    'baker_notes', 'ip_address', 'user_agent',
])]
class OrderInquiry extends Model
{
    /** @use HasFactory<OrderInquiryFactory> */
    use Auditable, HasFactory;

    /**
     * The prefix on every order reference.
     */
    public const string REFERENCE_PREFIX = 'HH-';

    /**
     * New rows start as new inquiries. Declared here as well as in the
     * migration because the database default only applies on INSERT, and an
     * unsaved model is rendered in the email previews.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'new',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fulfilment' => Fulfilment::class,
            'needed_on' => 'immutable_date',
            'occasion' => Occasion::class,
            'items' => 'array',
            'estimated_total_cents' => 'integer',
            'status' => InquiryStatus::class,
            'quoted_total_cents' => 'integer',
        ];
    }

    /**
     * The customer's own details, kept out of the audit trail.
     *
     * The same reasoning as ContactSubmission::auditExclude(): an audit entry
     * outlives the row and nobody may delete one, so copying a customer's
     * address or allergies into it would mean deleting an inquiry no longer
     * erases them. What remains is what the trail is for -- that order HH-X
     * was quoted, confirmed or deleted, by whom and when.
     *
     * @return list<string>
     */
    protected function auditExclude(): array
    {
        return [
            'name', 'email', 'phone', 'delivery_address', 'items', 'details', 'allergies',
            'baker_notes', 'ip_address', 'user_agent', 'updated_at',
        ];
    }

    /**
     * A fresh, human-friendly reference such as HH-7K3Q9P.
     *
     * Drawn from an alphabet without the characters people misread over the
     * phone (0/O, 1/I/L), and checked against the table so a collision can
     * never reach the unique index.
     */
    public static function newReference(): string
    {
        do {
            $code = collect(range(1, 6))
                ->map(fn (): string => Str::substr('23456789ABCDEFGHJKMNPQRSTUVWXYZ', random_int(0, 30), 1))
                ->implode('');

            $reference = self::REFERENCE_PREFIX.$code;
        } while (self::query()->where('reference', $reference)->exists());

        return $reference;
    }

    /**
     * Limit the query to inquiries nobody has answered yet.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function awaitingReply(Builder $query): void
    {
        $query->where('status', InquiryStatus::New);
    }

    /**
     * Limit the query to orders still on the schedule from today onwards.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function upcoming(Builder $query): void
    {
        $query->whereIn('status', [InquiryStatus::Quoted, InquiryStatus::Confirmed])
            ->whereDate('needed_on', '>=', today());
    }

    /**
     * How many items the inquiry asks for, across every line.
     */
    public function itemCount(): int
    {
        return (int) collect($this->items)->sum('quantity');
    }

    /**
     * A one-line summary of the lines, such as "2 × Cinnamon Rolls, 1 × Carrot Cake".
     */
    public function itemSummary(): string
    {
        return collect($this->items)
            ->map(fn (array $line): string => $line['quantity'].' × '.$line['name'])
            ->implode(', ');
    }

    /**
     * The estimate the customer was shown, formatted.
     */
    public function formattedEstimate(): string
    {
        return Money::format($this->estimated_total_cents);
    }

    /**
     * The price the baker quoted, formatted, or null before one is set.
     */
    public function formattedQuote(): ?string
    {
        return $this->quoted_total_cents === null ? null : Money::format($this->quoted_total_cents);
    }
}
