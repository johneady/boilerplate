<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\GuardsFinancialRecord;
use App\Payments\Enums\Currency;
use App\Payments\Enums\DisputeStatus;
use App\Payments\Enums\Gateway;
use App\Payments\Enums\GatewayMode;
use App\Payments\Money;
use Carbon\CarbonImmutable;
use Database\Factories\DisputeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A chargeback or PayPal claim against a payment.
 *
 * Tracked, not fought here: evidence is submitted in the gateway's dashboard
 * (dashboardUrl()), and this row mirrors what the gateway says, written only by
 * App\Payments\Actions\ReconcileDispute. Which payment and gateway it belongs
 * to never changes, and it is never deleted.
 *
 * Money a dispute withdraws is not entered on the payment ledger: the payment
 * is not refunded -- the customer's bank took the money back, and may return
 * it if the dispute is won -- so the disputed amount is shown on the dispute
 * and the payment's own figures keep describing the payment.
 *
 * @property int $id
 * @property int $payment_id
 * @property Gateway $gateway
 * @property GatewayMode $mode
 * @property string $gateway_dispute_id
 * @property Currency $currency
 * @property int $amount
 * @property string|null $reason
 * @property DisputeStatus $status
 * @property CarbonImmutable|null $evidence_due_by
 * @property CarbonImmutable|null $closed_at
 * @property CarbonImmutable|null $opened_notified_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Payment|null $payment
 */
#[Fillable(['payment_id', 'gateway', 'mode', 'gateway_dispute_id', 'currency', 'amount', 'reason', 'status', 'evidence_due_by'])]
class Dispute extends Model
{
    /** @use HasFactory<DisputeFactory> */
    use Auditable, GuardsFinancialRecord, HasFactory;

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
            'currency' => Currency::class,
            'amount' => 'integer',
            'status' => DisputeStatus::class,
            'evidence_due_by' => 'immutable_datetime',
            'closed_at' => 'immutable_datetime',
            'opened_notified_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return list<string>
     */
    protected function writeOnceAttributes(): array
    {
        return ['payment_id', 'gateway', 'mode', 'gateway_dispute_id', 'currency'];
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

    public function money(): Money
    {
        return Money::of($this->amount, $this->currency);
    }

    /**
     * Where operators respond to the dispute.
     *
     * Stripe links to the dispute itself. PayPal links to its Resolution
     * Center, where the case is listed: it has no stable link to one case.
     */
    public function dashboardUrl(): string
    {
        $sandbox = $this->mode === GatewayMode::Sandbox;

        return match ($this->gateway) {
            Gateway::Stripe => 'https://dashboard.stripe.com/'.($sandbox ? 'test/' : '').'disputes/'.$this->gateway_dispute_id,
            default => 'https://www.'.($sandbox ? 'sandbox.' : '').'paypal.com/resolutioncenter',
        };
    }
}
