<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Voltiva\DrivingNeed;
use App\Voltiva\EnquiryStatus;
use Carbon\CarbonImmutable;
use Database\Factories\EnquiryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A customer enquiry from one of the site's forms -- the CRM record.
 *
 * Holds what the brief asks the CRM to record (customer, vehicle, date,
 * source, requirements, finance and registration interest, follow-up status)
 * plus the position of the automatic email sequence. Stored before any email
 * is attempted, like a contact submission: the row is the record, the emails
 * are a courtesy on top of it.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property int|null $vehicle_id
 * @property string|null $vehicle_name
 * @property string|null $location
 * @property list<string>|null $driving_needs
 * @property bool $finance_interest
 * @property bool $registration_interest
 * @property string|null $message
 * @property string $source
 * @property string $locale
 * @property EnquiryStatus $status
 * @property string|null $staff_notes
 * @property int $follow_up_step
 * @property CarbonImmutable|null $next_follow_up_at
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Vehicle|null $vehicle
 */
#[Fillable([
    'name', 'email', 'phone', 'vehicle_id', 'vehicle_name', 'location', 'driving_needs', 'finance_interest',
    'registration_interest', 'message', 'source', 'locale', 'status', 'staff_notes', 'ip_address', 'user_agent',
])]
class Enquiry extends Model
{
    /** @use HasFactory<EnquiryFactory> */
    use Auditable, HasFactory;

    /**
     * The form placements an enquiry can come from, with their labels.
     *
     * @var array<string, string>
     */
    public const array SOURCES = [
        'vehicle' => 'Car page',
        'home' => 'Home page',
        'range' => 'Car range page',
        'register' => 'Register your interest page',
        'compare' => 'Compare cars',
        'finder' => 'Find your car',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'follow_up_step' => 0,
        'status' => 'new',
        'source' => 'register',
        'locale' => 'en',
    ];

    /**
     * A new enquiry is due its first sequence email straight away.
     *
     * Set here rather than left to the form's own send, so a first email that
     * fails (SMTP down) is still due and the scheduled command retries it,
     * instead of the sequence silently never starting.
     */
    protected static function booted(): void
    {
        static::creating(function (Enquiry $enquiry): void {
            if ($enquiry->follow_up_step === 0 && $enquiry->next_follow_up_at === null) {
                $enquiry->next_follow_up_at = now();
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
            'status' => EnquiryStatus::class,
            'driving_needs' => 'array',
            'finance_interest' => 'boolean',
            'registration_interest' => 'boolean',
            'follow_up_step' => 'integer',
            'next_follow_up_at' => 'datetime',
        ];
    }

    /**
     * The customer's own words and contact details stay out of the audit trail
     * for the reason ContactSubmission gives: an audit entry outlives the row,
     * and deleting an enquiry must erase the person who sent it.
     *
     * @return list<string>
     */
    protected function auditExclude(): array
    {
        return ['name', 'email', 'phone', 'location', 'message', 'ip_address', 'user_agent', 'updated_at'];
    }

    /**
     * The car the customer asked about.
     *
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * Limit the query to enquiries whose next sequence email is due.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function followUpDue(Builder $query): void
    {
        $query->whereNotNull('next_follow_up_at')->where('next_follow_up_at', '<=', now());
    }

    /**
     * The number of emails in the automatic sequence.
     */
    public static function followUpCount(): int
    {
        return count(self::followUpDays());
    }

    /**
     * The sequence, as days after the enquiry arrived.
     *
     * @return list<int>
     */
    public static function followUpDays(): array
    {
        /** @var list<int> $days */
        $days = array_values(array_map('intval', (array) config('voltiva.follow_up_days')));

        return $days;
    }

    /**
     * Record that sequence email number $step (1-based) went out, and work
     * out when the next one is due.
     *
     * The next date is measured from when the enquiry arrived, not from this
     * send, so a scheduler that was down for a day catches up rather than
     * pushing the whole sequence back.
     */
    public function recordFollowUpSent(int $step): void
    {
        $days = self::followUpDays();
        $createdAt = $this->created_at ?? now();

        $this->follow_up_step = $step;
        $this->next_follow_up_at = array_key_exists($step, $days) && $this->status->receivesFollowUps()
            ? $createdAt->addDays($days[$step])
            : null;

        $this->save();
    }

    /**
     * The sequence as the admin panel shows it: each email with its day,
     * when it went out or is due, and whether it was sent.
     *
     * @return list<array{step: int, day: int, date: CarbonImmutable, state: string}>
     */
    public function followUpTimeline(): array
    {
        $createdAt = $this->created_at ?? CarbonImmutable::now();
        $timeline = [];

        foreach (self::followUpDays() as $index => $day) {
            $step = $index + 1;

            $timeline[] = [
                'step' => $step,
                'day' => $day,
                'date' => $createdAt->addDays($day),
                'state' => match (true) {
                    $step <= $this->follow_up_step => 'sent',
                    // Closed by the team, or the customer used the link to
                    // stop the emails -- nothing further will go out.
                    ! $this->status->receivesFollowUps(), $this->next_follow_up_at === null => 'stopped',
                    default => 'scheduled',
                },
            ];
        }

        return $timeline;
    }

    /**
     * The driving needs the customer ticked, as enum cases.
     *
     * @return list<DrivingNeed>
     */
    public function drivingNeeds(): array
    {
        return array_values(array_filter(array_map(
            fn (string $value): ?DrivingNeed => DrivingNeed::tryFrom($value),
            $this->driving_needs ?? [],
        )));
    }

    /**
     * The label of the form this enquiry came from.
     */
    public function sourceLabel(): string
    {
        return self::SOURCES[$this->source] ?? $this->source;
    }
}
