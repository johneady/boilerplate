<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\GuardsFinancialRecord;
use App\Payments\Enums\CaptureMethod;
use App\Payments\Enums\Currency;
use App\Payments\Enums\Gateway;
use App\Payments\Enums\GatewayMode;
use App\Payments\Enums\ManualPaymentMethod;
use App\Payments\Enums\PaymentStatus;
use App\Payments\Enums\RefundStatus;
use App\Payments\Money;
use App\Payments\Tax\TaxLine;
use Carbon\CarbonImmutable;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * One attempt to pay for a payable, through one gateway.
 *
 * A financial record: the amounts, currency, customer, gateway and mode are
 * write-once, gateway ids may be filled in once, and the row can never be
 * deleted (App\Concerns\GuardsFinancialRecord). Status only moves forward,
 * through App\Payments\Actions\ReconcilePayment and its siblings.
 *
 * amount_captured and amount_refunded are projections of the
 * payment_transactions ledger, recomputed under a row lock whenever the
 * ledger changes -- the ledger is the record, these are a convenience.
 *
 * @property int $id
 * @property string $uuid
 * @property string $idempotency_key
 * @property string $payable_type
 * @property int $payable_id
 * @property int|null $user_id
 * @property string $customer_name
 * @property string $customer_email
 * @property string $description
 * @property Gateway $gateway
 * @property GatewayMode $mode
 * @property PaymentStatus $status
 * @property CaptureMethod $capture_method
 * @property Currency $currency
 * @property int $subtotal
 * @property int $tax_total
 * @property int $amount
 * @property int $amount_captured
 * @property int $amount_refunded
 * @property list<array{name: string, percentage: string, amount: int}> $tax_lines
 * @property string|null $gateway_checkout_id
 * @property string|null $gateway_payment_id
 * @property string|null $gateway_authorization_id
 * @property string|null $checkout_url
 * @property CarbonImmutable|null $authorized_at
 * @property CarbonImmutable|null $authorization_expires_at
 * @property CarbonImmutable|null $captured_at
 * @property CarbonImmutable|null $paid_at
 * @property CarbonImmutable|null $receipt_sent_at
 * @property CarbonImmutable|null $expiry_alerted_at
 * @property CarbonImmutable|null $failed_at
 * @property string|null $failure_reason
 * @property CarbonImmutable|null $voided_at
 * @property CarbonImmutable|null $expired_at
 * @property CarbonImmutable|null $last_reconciled_at
 * @property ManualPaymentMethod|null $manual_method
 * @property string|null $manual_reference
 * @property CarbonImmutable|null $manual_received_on
 * @property int|null $recorded_by
 * @property array<string, mixed>|null $metadata
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable([
    'idempotency_key', 'user_id', 'customer_name', 'customer_email', 'description',
    'gateway', 'mode', 'status', 'capture_method', 'currency', 'subtotal', 'tax_total',
    'amount', 'tax_lines', 'manual_method', 'manual_reference', 'manual_received_on',
    'recorded_by', 'metadata',
])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use Auditable, GuardsFinancialRecord, HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Payment $payment): void {
            if (blank($payment->uuid)) {
                $payment->uuid = (string) Str::uuid();
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
            'status' => PaymentStatus::class,
            'capture_method' => CaptureMethod::class,
            'currency' => Currency::class,
            'manual_method' => ManualPaymentMethod::class,
            'subtotal' => 'integer',
            'tax_total' => 'integer',
            'amount' => 'integer',
            'amount_captured' => 'integer',
            'amount_refunded' => 'integer',
            'tax_lines' => 'array',
            'metadata' => 'array',
            'authorized_at' => 'immutable_datetime',
            'authorization_expires_at' => 'immutable_datetime',
            'captured_at' => 'immutable_datetime',
            'paid_at' => 'immutable_datetime',
            'receipt_sent_at' => 'immutable_datetime',
            'expiry_alerted_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
            'voided_at' => 'immutable_datetime',
            'expired_at' => 'immutable_datetime',
            'last_reconciled_at' => 'immutable_datetime',
            'manual_received_on' => 'immutable_date',
        ];
    }

    /**
     * @return list<string>
     */
    protected function writeOnceAttributes(): array
    {
        return [
            'uuid', 'idempotency_key', 'payable_type', 'payable_id', 'customer_name',
            'customer_email', 'description', 'gateway', 'mode', 'capture_method', 'currency',
            'subtotal', 'tax_total', 'amount', 'tax_lines', 'manual_method', 'manual_reference',
            'manual_received_on', 'recorded_by',
        ];
    }

    /**
     * @return list<string>
     */
    protected function setOnceAttributes(): array
    {
        return ['gateway_checkout_id', 'gateway_payment_id', 'gateway_authorization_id'];
    }

    /**
     * Kept out of the audit trail.
     *
     * The customer's name and email are somebody else's personal data (the
     * same reasoning as ContactSubmission::auditExclude()). The rest is noise:
     * reconciliation bookkeeping and the Demo gateway's simulated state.
     *
     * @return list<string>
     */
    protected function auditExclude(): array
    {
        return ['customer_name', 'customer_email', 'checkout_url', 'metadata', 'last_reconciled_at', 'updated_at'];
    }

    /**
     * The attribute that identifies a payment in a URL.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The administrator who recorded a manual payment.
     *
     * @return BelongsTo<User, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * The ledger: every movement of money on this payment.
     *
     * @return HasMany<PaymentTransaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    /**
     * @return HasMany<Refund, $this>
     */
    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    /**
     * Chargebacks and PayPal claims against this payment.
     *
     * @return HasMany<Dispute, $this>
     */
    public function disputes(): HasMany
    {
        return $this->hasMany(Dispute::class);
    }

    public function total(): Money
    {
        return Money::of($this->amount, $this->currency);
    }

    public function subtotalMoney(): Money
    {
        return Money::of($this->subtotal, $this->currency);
    }

    public function taxTotalMoney(): Money
    {
        return Money::of($this->tax_total, $this->currency);
    }

    public function capturedMoney(): Money
    {
        return Money::of($this->amount_captured, $this->currency);
    }

    public function refundedMoney(): Money
    {
        return Money::of($this->amount_refunded, $this->currency);
    }

    /**
     * What can still be refunded: captured, less refunded, less refunds
     * already requested but not yet confirmed.
     *
     * Counting pending refunds is what stops two refunds requested moments
     * apart from together exceeding what was taken. Read it under the
     * payment's row lock when deciding whether to allow a refund.
     */
    public function refundableMoney(): Money
    {
        $pending = (int) $this->refunds()->where('status', RefundStatus::Pending->value)->sum('amount');

        return Money::of(max(0, $this->amount_captured - $this->amount_refunded - $pending), $this->currency);
    }

    /**
     * The taxes charged, as snapshotted when checkout started.
     *
     * @return list<TaxLine>
     */
    public function taxLines(): array
    {
        return array_map(fn (array $line): TaxLine => TaxLine::fromArray($line, $this->currency), $this->tax_lines);
    }

    /**
     * The customer-facing receipt and status page.
     *
     * Signed rather than guarded by a login: most customers pay as guests, so
     * the signature is what stops anybody holding a uuid from reading someone
     * else's name, email and purchase. Not temporary, because the link is
     * emailed in the receipt and must keep working.
     */
    public function receiptUrl(): string
    {
        return URL::signedRoute('payments.show', $this);
    }

    /**
     * Where the gateway sends the customer back to after paying.
     *
     * Signed, because it leads to the receipt: without the signature, anyone
     * holding the payment's uuid -- printed on the receipt as its reference --
     * could follow it to a freshly signed receipt URL. Gateways append their
     * own query parameters on the way back (PayPal's token and PayerID), which
     * PaymentReturnController ignores when checking the signature.
     */
    public function returnUrl(): string
    {
        return URL::signedRoute('payments.return', $this);
    }

    /**
     * Where the gateway sends a customer who backed out. Signed, like returnUrl().
     */
    public function cancelUrl(): string
    {
        return URL::signedRoute('payments.cancelled', $this);
    }

    /**
     * The idempotency key sent to the gateway for one operation on this payment.
     *
     * Deterministic, so a retried job or a resubmitted form sends the same key
     * and the gateway returns the original result instead of repeating it.
     */
    public function gatewayKey(string $operation): string
    {
        return "{$this->uuid}:{$operation}";
    }
}
