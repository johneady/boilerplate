<?php

namespace App\Models;

use App\Concerns\GuardsFinancialRecord;
use App\Payments\Enums\Currency;
use App\Payments\Enums\Gateway;
use App\Payments\Enums\TransactionSource;
use App\Payments\Enums\TransactionType;
use App\Payments\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One movement of money on a payment: the append-only ledger.
 *
 * Inserted, never updated, never deleted -- every attribute is write-once and
 * there is no updated_at column at all. The payment's captured and refunded
 * totals are recomputed from these rows, so the ledger is what the numbers
 * on the payment are checked against.
 *
 * Unique per (gateway, gateway_transaction_id), which is the idempotency
 * guard for incoming state: the same Stripe charge seen through the customer's
 * return AND through a webhook is inserted once, whichever arrives first.
 *
 * @property int $id
 * @property int $payment_id
 * @property TransactionType $type
 * @property int $amount
 * @property Currency $currency
 * @property Gateway $gateway
 * @property string $gateway_transaction_id
 * @property TransactionSource $source
 * @property CarbonImmutable $occurred_at
 * @property CarbonImmutable|null $created_at
 */
#[Fillable(['payment_id', 'type', 'amount', 'currency', 'gateway', 'gateway_transaction_id', 'source', 'occurred_at'])]
class PaymentTransaction extends Model
{
    use GuardsFinancialRecord;

    /**
     * No updated_at: a ledger row is never updated.
     */
    public const null UPDATED_AT = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'currency' => Currency::class,
            'gateway' => Gateway::class,
            'source' => TransactionSource::class,
            'amount' => 'integer',
            'occurred_at' => 'immutable_datetime',
        ];
    }

    /**
     * Every column: a ledger row is never updated.
     *
     * @return list<string>
     */
    protected function writeOnceAttributes(): array
    {
        return ['payment_id', 'type', 'amount', 'currency', 'gateway', 'gateway_transaction_id', 'source', 'occurred_at', 'created_at'];
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function money(): Money
    {
        return Money::of($this->amount, $this->currency);
    }
}
