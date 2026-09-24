<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\GuardsFinancialRecord;
use App\Payments\Enums\Currency;
use App\Payments\Enums\Gateway;
use App\Payments\Enums\GatewayMode;
use App\Payments\Enums\SubscriptionStatus;
use Carbon\CarbonImmutable;
use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * A user's subscription to a plan, billed by one gateway.
 *
 * Our own model over Stripe Billing and PayPal Subscriptions: the gateway
 * owns the billing schedule and the retries, and this row mirrors what the
 * gateway says, written only by App\Payments\Actions\ReconcileSubscription.
 * Every payment the gateway takes for it becomes an ordinary App\Models\Payment
 * whose payable is this subscription, so receipts and refunds work as they do
 * for one-time payments.
 *
 * One live subscription per user, enforced by the unique active_user_id
 * column: it holds user_id while the status is live and null once it has
 * ended. The gateway, mode and currency are write-once, the gateway ids are
 * set once, and the row can never be deleted.
 *
 * @property int $id
 * @property string $uuid
 * @property string $idempotency_key
 * @property int|null $user_id
 * @property int|null $active_user_id
 * @property int $plan_id
 * @property int $plan_price_id
 * @property int|null $pending_plan_price_id
 * @property Gateway $gateway
 * @property GatewayMode $mode
 * @property SubscriptionStatus $status
 * @property Currency $currency
 * @property string|null $gateway_subscription_id
 * @property string|null $gateway_customer_id
 * @property string|null $gateway_checkout_id
 * @property string|null $checkout_url
 * @property CarbonImmutable|null $trial_ends_at
 * @property CarbonImmutable|null $current_period_start
 * @property CarbonImmutable|null $current_period_end
 * @property bool $cancel_at_period_end
 * @property CarbonImmutable|null $canceled_at
 * @property CarbonImmutable|null $ends_at
 * @property CarbonImmutable|null $past_due_since
 * @property CarbonImmutable|null $started_notified_at
 * @property CarbonImmutable|null $trial_reminder_sent_at
 * @property CarbonImmutable|null $ended_notified_at
 * @property CarbonImmutable|null $last_reconciled_at
 * @property array<string, mixed>|null $metadata
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User|null $user
 * @property-read Plan|null $plan
 * @property-read PlanPrice|null $price
 * @property-read PlanPrice|null $pendingPrice
 */
#[Fillable([
    'idempotency_key', 'user_id', 'active_user_id', 'plan_id', 'plan_price_id',
    'gateway', 'mode', 'status', 'currency',
])]
class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use Auditable, GuardsFinancialRecord, HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Subscription $subscription): void {
            if (blank($subscription->uuid)) {
                $subscription->uuid = (string) Str::uuid();
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
            'gateway' => Gateway::class,
            'mode' => GatewayMode::class,
            'status' => SubscriptionStatus::class,
            'currency' => Currency::class,
            'cancel_at_period_end' => 'boolean',
            'trial_ends_at' => 'immutable_datetime',
            'current_period_start' => 'immutable_datetime',
            'current_period_end' => 'immutable_datetime',
            'canceled_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'past_due_since' => 'immutable_datetime',
            'started_notified_at' => 'immutable_datetime',
            'trial_reminder_sent_at' => 'immutable_datetime',
            'ended_notified_at' => 'immutable_datetime',
            'last_reconciled_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * @return list<string>
     */
    protected function writeOnceAttributes(): array
    {
        return ['uuid', 'idempotency_key', 'gateway', 'mode', 'currency'];
    }

    /**
     * @return list<string>
     */
    protected function setOnceAttributes(): array
    {
        return ['gateway_subscription_id', 'gateway_customer_id', 'gateway_checkout_id'];
    }

    /**
     * Reconciliation bookkeeping and the Demo gateway's simulated state are
     * noise in the audit trail.
     *
     * @return list<string>
     */
    protected function auditExclude(): array
    {
        return ['checkout_url', 'metadata', 'last_reconciled_at', 'updated_at'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * @return BelongsTo<PlanPrice, $this>
     */
    public function price(): BelongsTo
    {
        return $this->belongsTo(PlanPrice::class, 'plan_price_id');
    }

    /**
     * @return BelongsTo<PlanPrice, $this>
     */
    public function pendingPrice(): BelongsTo
    {
        return $this->belongsTo(PlanPrice::class, 'pending_plan_price_id');
    }

    /**
     * Every payment the gateway has taken for this subscription.
     *
     * @return MorphMany<Payment, $this>
     */
    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    /**
     * Whether the subscriber may use what they subscribed to right now.
     *
     * True while trialing or active; while past due, for the grace period
     * after the first failed renewal; and after a cancellation, until the
     * time already paid for runs out.
     */
    public function grantsAccess(int $graceDays): bool
    {
        $now = CarbonImmutable::now();

        return match ($this->status) {
            SubscriptionStatus::Trialing, SubscriptionStatus::Active => $this->ends_at === null || $this->ends_at->isAfter($now),
            SubscriptionStatus::PastDue => $this->graceEndsAt($graceDays)?->isAfter($now) ?? false,
            SubscriptionStatus::Canceled => $this->ends_at?->isAfter($now) ?? false,
            SubscriptionStatus::Incomplete, SubscriptionStatus::Expired => false,
        };
    }

    /**
     * When a past-due subscriber loses access, or null when not past due.
     */
    public function graceEndsAt(int $graceDays): ?CarbonImmutable
    {
        return $this->status === SubscriptionStatus::PastDue && $this->past_due_since !== null
            ? $this->past_due_since->addDays($graceDays)
            : null;
    }

    /**
     * Whether a cancellation is scheduled for the period end and may still
     * be taken back.
     */
    public function isCancelScheduled(): bool
    {
        return $this->cancel_at_period_end && $this->status->isLive();
    }

    /**
     * Where the gateway sends the customer back to after subscribing.
     *
     * Signed, like Payment::returnUrl(); the parameters gateways append are
     * ignored when the signature is checked.
     */
    public function returnUrl(): string
    {
        return URL::signedRoute('subscriptions.return', $this);
    }

    public function cancelUrl(): string
    {
        return URL::signedRoute('subscriptions.cancelled', $this);
    }

    /**
     * The idempotency key sent to the gateway for one operation on this
     * subscription.
     */
    public function gatewayKey(string $operation): string
    {
        return "{$this->uuid}:{$operation}";
    }
}
