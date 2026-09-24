<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\GuardsFinancialRecord;
use App\Payments\Enums\Currency;
use App\Payments\Enums\Gateway;
use App\Payments\Enums\RefundStatus;
use App\Payments\Money;
use Carbon\CarbonImmutable;
use Database\Factories\RefundFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A request to return money on a payment, and what came of it.
 *
 * Written BEFORE the gateway is asked (as pending, carrying its idempotency
 * key), so a process that dies mid-refund leaves a row saying so -- which
 * payments:reconcile-stale finishes by asking again with the same key.
 * The ledger entry is only written once the gateway confirms.
 *
 * @property int $id
 * @property string $uuid
 * @property string $idempotency_key
 * @property int $payment_id
 * @property Gateway $gateway
 * @property string|null $gateway_refund_id
 * @property Currency $currency
 * @property int $amount
 * @property int $tax_amount
 * @property string|null $reason
 * @property RefundStatus $status
 * @property string|null $failure_reason
 * @property int|null $initiated_by
 * @property CarbonImmutable|null $notified_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['idempotency_key', 'payment_id', 'gateway', 'gateway_refund_id', 'currency', 'amount', 'tax_amount', 'reason', 'status', 'failure_reason', 'initiated_by'])]
class Refund extends Model
{
    /** @use HasFactory<RefundFactory> */
    use Auditable, GuardsFinancialRecord, HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Refund $refund): void {
            if (blank($refund->uuid)) {
                $refund->uuid = (string) Str::uuid();
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
            'currency' => Currency::class,
            'status' => RefundStatus::class,
            'amount' => 'integer',
            'tax_amount' => 'integer',
            'notified_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return list<string>
     */
    protected function writeOnceAttributes(): array
    {
        return ['uuid', 'idempotency_key', 'payment_id', 'gateway', 'currency', 'amount', 'tax_amount', 'reason', 'initiated_by'];
    }

    /**
     * @return list<string>
     */
    protected function setOnceAttributes(): array
    {
        return ['gateway_refund_id'];
    }

    /**
     * @return list<string>
     */
    protected function auditExclude(): array
    {
        return ['updated_at'];
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * The administrator who asked for the refund; null when it was issued in
     * the gateway's own dashboard.
     *
     * @return BelongsTo<User, $this>
     */
    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    public function money(): Money
    {
        return Money::of($this->amount, $this->currency);
    }

    public function taxMoney(): Money
    {
        return Money::of($this->tax_amount, $this->currency);
    }
}
